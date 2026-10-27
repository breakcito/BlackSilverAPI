<?php

namespace App\Modules\CompraCarbon\Data;

use App\Models\AnticipoProveedor;
use App\Models\CargaCompraCarbon;
use App\Models\ComprobanteCompraCarbon;
use App\Models\ComprobanteTransporteCarbon;
use App\Models\PagoCompraCarbon;
use App\Models\PagoTransporteCarbon;
use App\Models\TransaccionAnticipoProveedor;
use App\Shared\Enums\CompraCarbon\EstadoCargaCompraCarbon;
use App\Shared\Enums\CompraCarbon\EstadoCompraCarbon;
use App\Shared\Enums\CompraCarbon\EstadoComprobanteCarbon;
use Illuminate\Support\Facades\DB;

class CompraCarbonPagosData
{
    /**
     * Registra un comprobante del proveedor (cuando la compra aplica IGV).
     *
     * Cascada contable: subtotal -> base imponible -> IGV -> importe total ->
     * detraccion -> monto neto. Los porcentajes (IGV y detraccion) los
     * gobierna la compra, no el comprobante.
     *
     * @param array{
     *   id_compra_carbon: int,
     *   id_empleado_registro: int,
     *   codigo_comprobante: string,
     *   fecha_emision: string,
     *   observacion?: string|null,
     *   evidencias?: mixed,
     *   ids_cargas: array<int>,
     *   anticipos?: array<int, array{id_anticipo_proveedor: int, monto_retirado: float}>
     * } $d
     */
    public static function registrar_comprobante_proveedor(array $d): int
    {
        return DB::transaction(function () use ($d) {
            $idCompra = (int) $d['id_compra_carbon'];
            $idsCargas = $d['ids_cargas'];

            $compra = DB::table('compra_carbon')->where('id', $idCompra)->first();
            if ($compra === null) {
                throw new \RuntimeException('Compra no encontrada');
            }
            $porcentajeIgv = (float) $compra->porcentaje_igv;
            $porcentajeDetraccion = (float) $compra->porcentaje_detraccion;

            // Subtotal: suma del subtotal de las cargas seleccionadas.
            $totalCargas = (float) DB::table('carga_compra_carbon')
                ->where('id_compra_carbon', $idCompra)
                ->whereIn('id', $idsCargas)
                ->sum('subtotal');

            // Procesar anticipos si se proporcionaron
            $anticipos = $d['anticipos'] ?? [];
            $totalAnticipos = 0.0;
            $transaccionesAnticipos = [];

            foreach ($anticipos as $ant) {
                $idAnt = (int) $ant['id_anticipo_proveedor'];
                $montoRetirado = round((float) $ant['monto_retirado'], 2);
                if ($montoRetirado <= 0) {
                    continue;
                }

                $antDb = DB::table('anticipo_proveedor')->where('id', $idAnt)->lockForUpdate()->first();
                if ($antDb === null) {
                    throw new \RuntimeException("Anticipo {$idAnt} no encontrado");
                }
                $saldoActual = (float) $antDb->saldo_actual;
                if ($saldoActual < $montoRetirado - 0.01) {
                    throw new \RuntimeException("Saldo insuficiente en el anticipo {$idAnt}. Saldo disponible: {$saldoActual}");
                }

                $nuevoSaldo = max(0.0, round($saldoActual - $montoRetirado, 2));
                $nuevoEstado = $nuevoSaldo <= 0.001 ? 'Sin Saldo' : 'Con Saldo';

                DB::table('anticipo_proveedor')
                    ->where('id', $idAnt)
                    ->update([
                        'saldo_actual' => $nuevoSaldo,
                        'estado' => $nuevoEstado,
                    ]);

                $totalAnticipos += $montoRetirado;
                $transaccionesAnticipos[] = [
                    'id_anticipo_proveedor' => $idAnt,
                    'monto_retirado' => $montoRetirado,
                ];
            }

            // Los anticipos amortizados reducen la base imponible.
            $baseImponible = max(0.0, round($totalCargas - $totalAnticipos, 2));
            $montoIgv = $porcentajeIgv > 0 ? round($baseImponible * ($porcentajeIgv / 100), 2) : 0.0;
            $importeTotal = round($baseImponible + $montoIgv, 2);
            $montoDetraccion = $porcentajeDetraccion > 0
                ? round($importeTotal * ($porcentajeDetraccion / 100), 2)
                : 0.0;
            $montoNeto = round($importeTotal - $montoDetraccion, 2);

            $evidenciasJson = isset($d['evidencias']) && $d['evidencias'] !== null
                ? (is_string($d['evidencias']) ? $d['evidencias'] : json_encode($d['evidencias'], JSON_UNESCAPED_UNICODE))
                : null;

            $idComprobante = ComprobanteCompraCarbon::insertGetId([
                'id_empleado_registro' => (int) $d['id_empleado_registro'],
                'id_compra_carbon' => $idCompra,
                'codigo_comprobante' => (string) $d['codigo_comprobante'],
                'fecha_emision' => (string) $d['fecha_emision'],
                'observacion' => $d['observacion'] ?? null,
                'evidencias' => $evidenciasJson,
                'subtotal' => round($totalCargas, 2),
                'monto_pagado_anticipos' => $totalAnticipos,
                'base_imponible' => $baseImponible,
                'monto_igv' => $montoIgv,
                'importe_total' => $importeTotal,
                'monto_detraccion' => $montoDetraccion,
                'monto_neto' => $montoNeto,
                'avance_pago_detraccion' => 0.0,
                'avance_pago_neto' => 0.0,
                'created_at' => now()->toDateTimeString(),
                'estado' => EstadoComprobanteCarbon::PendienteDePago->value,
            ]);

            // Asignar transacciones de anticipo con id_comprobante_compra_carbon
            foreach ($transaccionesAnticipos as $t) {
                TransaccionAnticipoProveedor::insert([
                    'id_anticipo_proveedor' => $t['id_anticipo_proveedor'],
                    'id_compra_carbon' => $idCompra,
                    'id_comprobante_compra_carbon' => $idComprobante,
                    'id_pago_compra_carbon' => null,
                    'monto_retirado' => $t['monto_retirado'],
                ]);
            }

            // Asignar el id_comprobante a las cargas seleccionadas
            DB::table('carga_compra_carbon')
                ->where('id_compra_carbon', $idCompra)
                ->whereIn('id', $idsCargas)
                ->update(['id_comprobante_compra_carbon' => $idComprobante]);

            return $idComprobante;
        });
    }

    /**
     * Registra un pago de proveedor contra comprobante o directo.
     * @param array<string, mixed> $d
     */
    public static function registrar_pago_proveedor(array $d): int
    {
        return DB::transaction(function () use ($d) {
            $idCompra = (int) $d['id_compra_carbon'];
            $idComprobante = isset($d['id_comprobante_compra_carbon']) && (int) $d['id_comprobante_compra_carbon'] > 0
                ? (int) $d['id_comprobante_compra_carbon']
                : null;
            $esDetraccion = !empty($d['es_para_detraccion']);
            $montoPagado = round((float) $d['monto_pagado'], 2);

            $evidenciasJson = isset($d['evidencias']) && $d['evidencias'] !== null
                ? (is_string($d['evidencias']) ? $d['evidencias'] : json_encode($d['evidencias'], JSON_UNESCAPED_UNICODE))
                : null;

            // Comprobante (compra con IGV): el pago no puede exceder el saldo
            // pendiente, ni en la parte neta ni en la de detraccion.
            $cmp = null;
            if ($idComprobante !== null) {
                $cmp = DB::table('comprobante_compra_carbon')->where('id', $idComprobante)->lockForUpdate()->first();
                if ($cmp === null) {
                    throw new \RuntimeException('Comprobante no encontrado');
                }
                $pendiente = $esDetraccion
                    ? (float) $cmp->monto_detraccion - (float) $cmp->avance_pago_detraccion
                    : (float) $cmp->monto_neto - (float) $cmp->avance_pago_neto;
                $pendiente = max(0.0, round($pendiente, 2));
                if (round($montoPagado - $pendiente, 2) > 0.01) {
                    throw new \RuntimeException(
                        'El monto a pagar supera el saldo pendiente del comprobante (S/ ' . number_format($pendiente, 2) . ')'
                    );
                }
            } else {
                // Pago directo (compra sin IGV): el efectivo pagado mas los
                // anticipos amortizados no puede superar el importe de las
                // cargas seleccionadas.
                $idsCargas = $d['ids_cargas'] ?? [];
                $totalAnticipos = 0.0;
                foreach (($d['anticipos'] ?? []) as $ant) {
                    $totalAnticipos += round((float) ($ant['monto_retirado'] ?? 0), 2);
                }
                if (!empty($idsCargas)) {
                    $totalCobrar = (float) DB::table('carga_compra_carbon')
                        ->where('id_compra_carbon', $idCompra)
                        ->whereIn('id', $idsCargas)
                        ->sum('neto_a_pagar');
                    $totalCobrar = round($totalCobrar, 2);
                    if ($totalCobrar > 0 && round($montoPagado + $totalAnticipos - $totalCobrar, 2) > 0.01) {
                        throw new \RuntimeException(
                            'El monto a pagar mas los anticipos aplicados supera el importe de las cargas seleccionadas (S/ ' . number_format($totalCobrar, 2) . ')'
                        );
                    }
                }
            }

            $idPago = PagoCompraCarbon::insertGetId([
                'id_compra_carbon' => $idCompra,
                'id_comprobante_compra_carbon' => $idComprobante,
                'id_cuenta_bancaria_empresa' => isset($d['id_cuenta_bancaria_empresa']) && (int) $d['id_cuenta_bancaria_empresa'] > 0
                    ? (int) $d['id_cuenta_bancaria_empresa']
                    : null,
                'id_cuenta_bancaria_proveedor' => isset($d['id_cuenta_bancaria_proveedor']) && (int) $d['id_cuenta_bancaria_proveedor'] > 0
                    ? (int) $d['id_cuenta_bancaria_proveedor']
                    : null,
                'id_empleado_registro' => (int) $d['id_empleado_registro'],
                'medio_pago' => (string) $d['medio_pago'],
                'numero_operacion' => $d['numero_operacion'] ?? null,
                'fecha_hora_pago' => (string) $d['fecha_hora_pago'],
                'es_para_detraccion' => $esDetraccion ? 1 : 0,
                'observacion' => $d['observacion'] ?? null,
                'evidencias' => $evidenciasJson,
                'monto_pagado' => $montoPagado,
                'created_at' => now()->toDateTimeString(),
            ]);

            // Si es con comprobante: actualizar avances del comprobante
            if ($idComprobante !== null) {
                $nuevoAvanceDetraccion = (float) $cmp->avance_pago_detraccion;
                $nuevoAvanceNeto = (float) $cmp->avance_pago_neto;

                if ($esDetraccion) {
                    $nuevoAvanceDetraccion = round($nuevoAvanceDetraccion + $montoPagado, 2);
                } else {
                    $nuevoAvanceNeto = round($nuevoAvanceNeto + $montoPagado, 2);
                }

                $estadoCmp = EstadoComprobanteCarbon::resolver(
                    totalNeto: (float) $cmp->monto_neto,
                    montoDetraccion: (float) $cmp->monto_detraccion,
                    netoPagado: $nuevoAvanceNeto,
                    detraccionPagada: $nuevoAvanceDetraccion
                );

                DB::table('comprobante_compra_carbon')
                    ->where('id', $idComprobante)
                    ->update([
                        'avance_pago_detraccion' => $nuevoAvanceDetraccion,
                        'avance_pago_neto' => $nuevoAvanceNeto,
                        'estado' => $estadoCmp->value,
                    ]);

                // Si el comprobante quedó Pagado, marcar las cargas asociadas como Pagado
                if ($estadoCmp === EstadoComprobanteCarbon::Pagado) {
                    DB::table('carga_compra_carbon')
                        ->where('id_comprobante_compra_carbon', $idComprobante)
                        ->update(['estado' => EstadoCargaCompraCarbon::Pagado->value]);
                }
            } else {
                // Pago directo sin comprobante (compra sin IGV):
                // Asignar cargas seleccionadas y procesar anticipos si los hay
                $idsCargas = $d['ids_cargas'] ?? [];
                if (!empty($idsCargas)) {
                    DB::table('carga_compra_carbon')
                        ->where('id_compra_carbon', $idCompra)
                        ->whereIn('id', $idsCargas)
                        ->update([
                            'id_pago_compra_carbon' => $idPago,
                            'estado' => EstadoCargaCompraCarbon::Pagado->value,
                        ]);
                }

                $anticipos = $d['anticipos'] ?? [];
                foreach ($anticipos as $ant) {
                    $idAnt = (int) $ant['id_anticipo_proveedor'];
                    $montoRet = round((float) $ant['monto_retirado'], 2);
                    if ($montoRet <= 0)
                        continue;

                    $antDb = DB::table('anticipo_proveedor')->where('id', $idAnt)->lockForUpdate()->first();
                    if ($antDb !== null) {
                        $saldoAct = (float) $antDb->saldo_actual;
                        $nuevoSal = max(0.0, round($saldoAct - $montoRet, 2));
                        DB::table('anticipo_proveedor')->where('id', $idAnt)->update([
                            'saldo_actual' => $nuevoSal,
                            'estado' => $nuevoSal <= 0.001 ? 'Sin Saldo' : 'Con Saldo',
                        ]);
                    }

                    TransaccionAnticipoProveedor::insert([
                        'id_anticipo_proveedor' => $idAnt,
                        'id_compra_carbon' => $idCompra,
                        'id_comprobante_compra_carbon' => null,
                        'id_pago_compra_carbon' => $idPago,
                        'monto_retirado' => $montoRet,
                    ]);
                }
            }

            self::revisar_cierre_automatico_compra($idCompra);

            return $idPago;
        });
    }

    /**
     * Registra un comprobante de transporte/flete.
     *
     * Cascada contable del flete: subtotal -> IGV del flete -> importe total
     * -> detraccion del flete -> monto neto. Los porcentajes los gobierna la
     * compra de carbon.
     *
     * @param array<string, mixed> $d
     */
    public static function registrar_comprobante_transporte(array $d): int
    {
        return DB::transaction(function () use ($d) {
            $idCompra = (int) $d['id_compra_carbon'];
            $idTransportista = (int) $d['id_transportista'];
            $idsCargas = $d['ids_cargas'];

            $compra = DB::table('compra_carbon')->where('id', $idCompra)->first();
            if ($compra === null) {
                throw new \RuntimeException('Compra no encontrada');
            }
            $porcentajeIgvFlete = (float) $compra->porcentaje_igv_flete;
            $porcentajeDetraccionFlete = (float) $compra->porcentaje_detraccion_flete;

            // Subtotal: suma del flete subtotal de las cargas seleccionadas.
            $subtotal = (float) DB::table('carga_compra_carbon')
                ->where('id_compra_carbon', $idCompra)
                ->whereIn('id', $idsCargas)
                ->sum('flete_subtotal');

            $montoIgv = $porcentajeIgvFlete > 0 ? round($subtotal * ($porcentajeIgvFlete / 100), 2) : 0.0;
            $importeTotal = round($subtotal + $montoIgv, 2);
            $montoDetraccion = $porcentajeDetraccionFlete > 0
                ? round($importeTotal * ($porcentajeDetraccionFlete / 100), 2)
                : 0.0;
            $montoNeto = round($importeTotal - $montoDetraccion, 2);

            $evidenciasJson = isset($d['evidencias']) && $d['evidencias'] !== null
                ? (is_string($d['evidencias']) ? $d['evidencias'] : json_encode($d['evidencias'], JSON_UNESCAPED_UNICODE))
                : null;

            $idComprobante = ComprobanteTransporteCarbon::insertGetId([
                'id_compra_carbon' => $idCompra,
                'id_empleado_registro' => (int) $d['id_empleado_registro'],
                'id_transportista' => $idTransportista,
                'codigo_comprobante' => (string) $d['codigo_comprobante'],
                'fecha_emision' => (string) $d['fecha_emision'],
                'observacion' => $d['observacion'] ?? null,
                'evidencias' => $evidenciasJson,
                'subtotal' => round($subtotal, 2),
                'monto_igv' => $montoIgv,
                'importe_total' => $importeTotal,
                'monto_detraccion' => $montoDetraccion,
                'monto_neto' => $montoNeto,
                'avance_pago_detraccion' => 0.0,
                'avance_pago_neto' => 0.0,
                'created_at' => now()->toDateTimeString(),
                'estado' => EstadoComprobanteCarbon::PendienteDePago->value,
            ]);

            DB::table('carga_compra_carbon')
                ->where('id_compra_carbon', $idCompra)
                ->whereIn('id', $idsCargas)
                ->update(['id_comprobante_transporte_carbon' => $idComprobante]);

            return $idComprobante;
        });
    }

    /**
     * Registra un pago de transporte/flete.
     * @param array<string, mixed> $d
     */
    public static function registrar_pago_transporte(array $d): int
    {
        return DB::transaction(function () use ($d) {
            $idCompra = (int) $d['id_compra_carbon'];
            $idComprobanteTransporte = (int) $d['id_comprobante_transporte_carbon'];
            $esDetraccion = !empty($d['es_para_detraccion']);
            $montoPagado = round((float) $d['monto_pagado'], 2);

            $evidenciasJson = isset($d['evidencias']) && $d['evidencias'] !== null
                ? (is_string($d['evidencias']) ? $d['evidencias'] : json_encode($d['evidencias'], JSON_UNESCAPED_UNICODE))
                : null;

            $idPago = PagoTransporteCarbon::insertGetId([
                'id_compra_carbon' => $idCompra,
                'id_comprobante_transporte_carbon' => $idComprobanteTransporte,
                'id_cuenta_bancaria_empresa' => isset($d['id_cuenta_bancaria_empresa']) && (int) $d['id_cuenta_bancaria_empresa'] > 0
                    ? (int) $d['id_cuenta_bancaria_empresa']
                    : null,
                'id_cuenta_bancaria_transportista' => isset($d['id_cuenta_bancaria_transportista']) && (int) $d['id_cuenta_bancaria_transportista'] > 0
                    ? (int) $d['id_cuenta_bancaria_transportista']
                    : null,
                'id_empleado_registro' => (int) $d['id_empleado_registro'],
                'medio_pago' => (string) $d['medio_pago'],
                'numero_operacion' => $d['numero_operacion'] ?? null,
                'fecha_hora_pago' => (string) $d['fecha_hora_pago'],
                'es_para_detraccion' => $esDetraccion ? 1 : 0,
                'observacion' => $d['observacion'] ?? null,
                'evidencias' => $evidenciasJson,
                'monto_pagado' => $montoPagado,
                'created_at' => now()->toDateTimeString(),
            ]);

            $cmp = DB::table('comprobante_transporte_carbon')->where('id', $idComprobanteTransporte)->lockForUpdate()->first();
            if ($cmp === null) {
                throw new \RuntimeException('Comprobante de transporte no encontrado');
            }

            // El pago no puede exceder el saldo pendiente del comprobante.
            $pendiente = $esDetraccion
                ? (float) $cmp->monto_detraccion - (float) $cmp->avance_pago_detraccion
                : (float) $cmp->monto_neto - (float) $cmp->avance_pago_neto;
            $pendiente = max(0.0, round($pendiente, 2));
            if (round($montoPagado - $pendiente, 2) > 0.01) {
                throw new \RuntimeException(
                    'El monto a pagar supera el saldo pendiente del comprobante de transporte (S/ ' . number_format($pendiente, 2) . ')'
                );
            }

            $nuevoAvanceDetraccion = (float) $cmp->avance_pago_detraccion;
            $nuevoAvanceNeto = (float) $cmp->avance_pago_neto;

            if ($esDetraccion) {
                $nuevoAvanceDetraccion = round($nuevoAvanceDetraccion + $montoPagado, 2);
            } else {
                $nuevoAvanceNeto = round($nuevoAvanceNeto + $montoPagado, 2);
            }

            $estadoCmp = EstadoComprobanteCarbon::resolver(
                totalNeto: (float) $cmp->monto_neto,
                montoDetraccion: (float) $cmp->monto_detraccion,
                netoPagado: $nuevoAvanceNeto,
                detraccionPagada: $nuevoAvanceDetraccion
            );

            DB::table('comprobante_transporte_carbon')
                ->where('id', $idComprobanteTransporte)
                ->update([
                    'avance_pago_detraccion' => $nuevoAvanceDetraccion,
                    'avance_pago_neto' => $nuevoAvanceNeto,
                    'estado' => $estadoCmp->value,
                ]);

            return $idPago;
        });
    }

    /**
     * Si todas las cargas de la compra están Pagadas, pasa el estado de la compra a Pagado.
     */
    private static function revisar_cierre_automatico_compra(int $idCompra): void
    {
        $cargas = DB::table('carga_compra_carbon')->where('id_compra_carbon', $idCompra)->get();
        if ($cargas->isEmpty()) {
            return;
        }

        $todasPagadas = $cargas->every(fn($c) => $c->estado === EstadoCargaCompraCarbon::Pagado->value);
        if ($todasPagadas) {
            DB::table('compra_carbon')
                ->where('id', $idCompra)
                ->where('estado', '<>', EstadoCompraCarbon::Anulado->value)
                ->update(['estado' => EstadoCompraCarbon::Pagado->value]);
        }
    }
}
