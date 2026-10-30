<?php

namespace App\Modules\VentaCarbon\Data;

use App\Models\ComprobanteVentaCarbon;
use App\Models\LiquidacionVentaCarbon;
use App\Models\PagoVentaCarbon;
use App\Shared\Enums\VentaCarbon\EstadoCargaVentaCarbon;
use App\Shared\Enums\VentaCarbon\EstadoComprobanteVentaCarbon;
use App\Shared\Enums\VentaCarbon\EstadoLiquidacionVentaCarbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data de la cadena de facturacion de la venta de carbon:
 * Liquidacion -> Comprobante -> Pago.
 *
 * Es el unico lugar con SQL de esa cadena.
 */
class VentaCarbonPagosData
{
    /**
     * Las tablas de la cadena se crean con SQL plano: si todavia no existen,
     * el listado de ventas sigue funcionando y simply no muestra liquidaciones.
     */
    private static function existe_cadena(): bool
    {
        return Schema::hasTable('liquidacion_venta_carbon');
    }

    /**
     * Columnas que la liquidacion necesita para poder guardar.
     *
     * Si faltan, la liquidacion no se puede registrar: es preferible un
     * mensaje claro que el error 1054 de MySQL ("Unknown column").
     *
     * @return array<int, string> Columnas que faltan.
     */
    private static function columnas_pendientes(): array
    {
        $requeridas = [
            'id_liquidacion_venta_carbon',
            'fue_aceptado',
            'precio_materia_prima',
            'monto_igv',
            'total',
            'monto_detraccion',
            'neto_a_pagar',
        ];

        $faltantes = [];
        foreach ($requeridas as $columna) {
            if (!Schema::hasColumn('carga_venta_carbon', $columna)) {
                $faltantes[] = $columna;
            }
        }

        return $faltantes;
    }

    /**
     * Trae las liquidaciones de varias ventas con su comprobante y los pagos
     * de ese comprobante. Numero fijo de consultas, sin N+1.
     *
     * @param int[] $ids_venta
     * @return array<int, array<object>> Indexado por id de venta.
     */
    public static function get_liquidaciones_por_venta(array $ids_venta): array
    {
        if (empty($ids_venta) || !self::existe_cadena()) {
            return [];
        }

        $liquidaciones = self::get_liquidaciones($ids_venta);
        if (empty($liquidaciones)) {
            return [];
        }

        $idsLiquidacion = array_map(fn ($l) => (int) $l->id_liquidacion_venta_carbon, $liquidaciones);
        $comprobantes = self::get_comprobantes($idsLiquidacion);

        $idsComprobante = array_map(fn ($c) => (int) $c->id_comprobante_venta_carbon, $comprobantes);
        $pagosPorComprobante = self::get_pagos_por_comprobante($idsComprobante);

        foreach ($comprobantes as $comprobante) {
            $idComprobante = (int) $comprobante->id_comprobante_venta_carbon;
            $comprobante->pagos = $pagosPorComprobante[$idComprobante] ?? [];
        }

        $comprobantesPorLiquidacion = [];
        foreach ($comprobantes as $comprobante) {
            $comprobantesPorLiquidacion[(int) $comprobante->id_liquidacion_venta_carbon] = $comprobante;
        }

        $resultado = [];
        foreach ($liquidaciones as $liquidacion) {
            $idLiquidacion = (int) $liquidacion->id_liquidacion_venta_carbon;
            $liquidacion->comprobante = $comprobantesPorLiquidacion[$idLiquidacion] ?? null;
            $resultado[(int) $liquidacion->id_venta_carbon][] = $liquidacion;
        }

        return $resultado;
    }

    /**
     * Liquidaciones de las ventas indicadas.
     * @param int[] $ids_venta
     * @return array<object>
     */
    private static function get_liquidaciones(array $ids_venta): array
    {
        $placeholders = [];
        $params = [];
        foreach (array_values($ids_venta) as $i => $id) {
            $key = 'id_venta_liq_' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = (int) $id;
        }

        $sql = '
            SELECT
                lv.id AS id_liquidacion_venta_carbon,
                lv.id_venta_carbon,
                lv.id_empleado_registro,
                CONCAT(er.nombre, " ", er.apellido) AS empleado_registro,
                lv.id_empleado_confirmacion,
                CONCAT(ec.nombre, " ", ec.apellido) AS empleado_confirmacion,
                lv.id_tipo_carbon,
                tc.nombre AS tipo_carbon,
                tc.codigo AS tipo_carbon_codigo,
                lv.codigo_orden_compra,
                lv.numero_reporte,
                lv.fecha_aprobacion,
                lv.version,
                lv.evidencias,
                lv.subtotal,
                lv.monto_igv,
                lv.total,
                lv.monto_detraccion,
                lv.neto_a_pagar,
                lv.esta_confirmado,
                lv.fecha_hora_confirmacion,
                lv.created_at
            FROM liquidacion_venta_carbon lv
            LEFT JOIN empleado er ON er.id = lv.id_empleado_registro
            LEFT JOIN empleado ec ON ec.id = lv.id_empleado_confirmacion
            LEFT JOIN tipo_carbon tc ON tc.id = lv.id_tipo_carbon
            WHERE lv.id_venta_carbon IN (' . implode(',', $placeholders) . ')
            ORDER BY lv.id ASC
        ';

        $rows = DB::select($sql, $params);

        foreach ($rows as $row) {
            $row->evidencias = VentaCarbonData::decode_json($row->evidencias ?? null);
            $row->esta_confirmado = (int) ($row->esta_confirmado ?? 0) === 1;
            $row->estado = EstadoLiquidacionVentaCarbon::desdeConfirmado($row->esta_confirmado)->value;
        }

        return $rows;
    }

    /**
     * Comprobantes de las liquidaciones indicadas.
     * @param int[] $ids_liquidacion
     * @return array<object>
     */
    private static function get_comprobantes(array $ids_liquidacion): array
    {
        if (empty($ids_liquidacion)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($ids_liquidacion) as $i => $id) {
            $key = 'id_liq_cmp_' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = (int) $id;
        }

        $sql = '
            SELECT
                cmp.id AS id_comprobante_venta_carbon,
                cmp.id_empleado_registro,
                CONCAT(e.nombre, " ", e.apellido) AS empleado_registro,
                cmp.id_venta_carbon,
                cmp.id_liquidacion_venta_carbon,
                cmp.codigo_comprobante,
                cmp.fecha_emision,
                cmp.observacion,
                cmp.evidencias,
                cmp.subtotal,
                cmp.monto_pagado_anticipos,
                cmp.base_imponible,
                cmp.monto_igv,
                cmp.importe_total,
                cmp.monto_detraccion,
                cmp.monto_neto,
                cmp.avance_pago_neto,
                cmp.avance_pago_detraccion,
                cmp.created_at,
                cmp.estado
            FROM comprobante_venta_carbon cmp
            LEFT JOIN empleado e ON e.id = cmp.id_empleado_registro
            WHERE cmp.id_liquidacion_venta_carbon IN (' . implode(',', $placeholders) . ')
            ORDER BY cmp.id ASC
        ';

        $rows = DB::select($sql, $params);

        foreach ($rows as $row) {
            $row->evidencias = VentaCarbonData::decode_json($row->evidencias ?? null);
        }

        return $rows;
    }

    /**
     * Pagos de los comprobantes indicados.
     * @param int[] $ids_comprobante
     * @return array<int, array<object>> Indexado por id de comprobante.
     */
    private static function get_pagos_por_comprobante(array $ids_comprobante): array
    {
        if (empty($ids_comprobante)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($ids_comprobante) as $i => $id) {
            $key = 'id_cmp_pago_' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = (int) $id;
        }

        $sql = '
            SELECT
                p.id AS id_pago_venta_carbon,
                p.id_empleado_registro,
                CONCAT(e.nombre, " ", e.apellido) AS empleado_registro,
                p.id_venta_carbon,
                p.id_comprobante_venta_carbon,
                p.id_cuenta_bancaria_empresa,
                cbe.numero_cuenta AS empresa_numero_cuenta,
                be.nombre AS empresa_banco,
                be.abreviatura AS empresa_banco_abv,
                p.medio_pago,
                p.numero_operacion,
                p.fecha_hora_pago,
                p.es_para_detraccion,
                p.monto_pagado,
                p.observacion,
                p.evidencias,
                p.created_at
            FROM pago_venta_carbon p
            LEFT JOIN empleado e ON e.id = p.id_empleado_registro
            LEFT JOIN cuenta_bancaria_empresa cbe ON cbe.id = p.id_cuenta_bancaria_empresa
            LEFT JOIN banco be ON be.id = cbe.id_banco
            WHERE p.id_comprobante_venta_carbon IN (' . implode(',', $placeholders) . ')
            ORDER BY p.id ASC
        ';

        $rows = DB::select($sql, $params);

        $resultado = [];
        foreach ($rows as $row) {
            $row->evidencias = VentaCarbonData::decode_json($row->evidencias ?? null);
            $row->es_para_detraccion = (int) ($row->es_para_detraccion ?? 0) === 1;
            $resultado[(int) $row->id_comprobante_venta_carbon][] = $row;
        }

        return $resultado;
    }

    /**
     * Registra una liquidacion con sus cargas y recalcula los totales.
     *
     * @param array<string, mixed> $d
     */
    public static function registrar_liquidacion(array $d): int
    {
        return DB::transaction(function () use ($d) {
            $idLiquidacion = LiquidacionVentaCarbon::insertGetId([
                'id_venta_carbon' => (int) $d['id_venta_carbon'],
                'id_empleado_registro' => (int) $d['id_empleado_registro'],
                'id_empleado_confirmacion' => null,
                'id_tipo_carbon' => (int) $d['id_tipo_carbon'],
                'codigo_orden_compra' => isset($d['codigo_orden_compra']) && $d['codigo_orden_compra'] !== ''
                    ? (string) $d['codigo_orden_compra']
                    : null,
                'numero_reporte' => isset($d['numero_reporte']) && $d['numero_reporte'] !== ''
                    ? (string) $d['numero_reporte']
                    : null,
                'fecha_aprobacion' => isset($d['fecha_aprobacion']) && $d['fecha_aprobacion'] !== ''
                    ? (string) $d['fecha_aprobacion']
                    : null,
                'version' => isset($d['version']) && $d['version'] !== '' ? (string) $d['version'] : null,
                'evidencias' => self::encode_json($d['evidencias'] ?? null),
                'subtotal' => 0,
                'monto_igv' => 0,
                'total' => 0,
                'monto_detraccion' => 0,
                'neto_a_pagar' => 0,
                'esta_confirmado' => 0,
                'fecha_hora_confirmacion' => null,
                'created_at' => now()->toDateTimeString(),
            ]);

            self::asignar_cargas(
                idLiquidacion: $idLiquidacion,
                idVenta: (int) $d['id_venta_carbon'],
                idTipoCarbon: (int) $d['id_tipo_carbon'],
                cargas: $d['cargas'] ?? [],
            );

            self::recalcular_liquidacion($idLiquidacion);

            return $idLiquidacion;
        });
    }

    /**
     * Actualiza la cabecera y los precios de las cargas de una liquidacion.
     *
     * @param array<string, mixed> $d
     */
    public static function actualizar_liquidacion(array $d): void
    {
        DB::transaction(function () use ($d) {
            $idLiquidacion = (int) $d['id_liquidacion_venta_carbon'];

            $liquidacion = DB::table('liquidacion_venta_carbon')
                ->where('id', $idLiquidacion)
                ->lockForUpdate()
                ->first();

            if ($liquidacion === null) {
                throw new \RuntimeException('La liquidacion no existe');
            }

            $actualizaciones = [
                'id_tipo_carbon' => (int) $d['id_tipo_carbon'],
                'codigo_orden_compra' => isset($d['codigo_orden_compra']) && $d['codigo_orden_compra'] !== ''
                    ? (string) $d['codigo_orden_compra']
                    : null,
                'numero_reporte' => isset($d['numero_reporte']) && $d['numero_reporte'] !== ''
                    ? (string) $d['numero_reporte']
                    : null,
                'fecha_aprobacion' => isset($d['fecha_aprobacion']) && $d['fecha_aprobacion'] !== ''
                    ? (string) $d['fecha_aprobacion']
                    : null,
                'version' => isset($d['version']) && $d['version'] !== '' ? (string) $d['version'] : null,
            ];

            if (array_key_exists('evidencias', $d)) {
                $actualizaciones['evidencias'] = self::encode_json($d['evidencias']);
            }

            DB::table('liquidacion_venta_carbon')
                ->where('id', $idLiquidacion)
                ->update($actualizaciones);

            // Las cargas que salen de la liquidacion vuelven a quedar libres.
            $idsActuales = DB::table('carga_venta_carbon')
                ->where('id_liquidacion_venta_carbon', $idLiquidacion)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $idsNuevos = array_map(
                fn ($c) => (int) ($c['id_carga_venta_carbon'] ?? 0),
                $d['cargas'] ?? []
            );

            $idsRetirados = array_diff($idsActuales, $idsNuevos);
            if (!empty($idsRetirados)) {
                DB::table('carga_venta_carbon')
                    ->whereIn('id', $idsRetirados)
                    ->update([
                        'id_liquidacion_venta_carbon' => null,
                        'precio_base' => 0,
                        'descuento' => 0,
                        'bono' => 0,
                        'precio_materia_prima' => 0,
                        'subtotal' => 0,
                        'monto_igv' => 0,
                        'total' => 0,
                        'monto_detraccion' => 0,
                        'neto_a_pagar' => 0,
                        'estado' => EstadoCargaVentaCarbon::LlegadaConfirmada->value,
                    ]);
            }

            self::asignar_cargas(
                idLiquidacion: $idLiquidacion,
                idVenta: (int) $liquidacion->id_venta_carbon,
                idTipoCarbon: (int) $d['id_tipo_carbon'],
                cargas: $d['cargas'] ?? [],
            );

            self::recalcular_liquidacion($idLiquidacion);

            // Si ya habia un comprobante, sus importes se recalculan. El
            // Service bloquea el caso en que ya haya pagos registrados.
            $comprobante = DB::table('comprobante_venta_carbon')
                ->where('id_liquidacion_venta_carbon', $idLiquidacion)
                ->first();

            if ($comprobante !== null) {
                self::recalcular_comprobante((int) $comprobante->id);
            }
        });
    }

    /**
     * Confirma la liquidacion: ya no se pueden cambiar sus precios sin
     * rehacer los calculos del comprobante.
     */
    public static function confirmar_liquidacion(int $id_liquidacion, int $id_empleado): void
    {
        DB::transaction(function () use ($id_liquidacion, $id_empleado) {
            $liquidacion = DB::table('liquidacion_venta_carbon')
                ->where('id', $id_liquidacion)
                ->lockForUpdate()
                ->first();

            if ($liquidacion === null) {
                throw new \RuntimeException('La liquidacion no existe');
            }

            if ((int) $liquidacion->esta_confirmado === 1) {
                throw new \RuntimeException('La liquidacion ya fue confirmada');
            }

            DB::table('liquidacion_venta_carbon')
                ->where('id', $id_liquidacion)
                ->update([
                    'esta_confirmado' => 1,
                    'id_empleado_confirmacion' => $id_empleado,
                    'fecha_hora_confirmacion' => now()->toDateTimeString(),
                ]);
        });
    }

    /**
     * Enlaza las cargas a la liquidacion y recalcula el importe de cada una.
     *
     * @param array<int, array<string, mixed>> $cargas
     */
    private static function asignar_cargas(
        int $idLiquidacion,
        int $idVenta,
        int $idTipoCarbon,
        array $cargas
    ): void {
        // $faltantes = self::columnas_pendientes();
        // if (!empty($faltantes)) {
        //     throw new \RuntimeException(
        //         'No se puede registrar la liquidacion: faltan las columnas '
        //         . implode(', ', $faltantes)
        //         . ' en carga_venta_carbon. Aplica el script SQL de la liquidacion.'
        //     );
        // }

        $porcentajeIgv = self::porcentaje_igv_venta($idVenta);
        $porcentajeDetraccion = self::porcentaje_detraccion_venta($idVenta);

        foreach ($cargas as $c) {
            $idCarga = (int) $c['id_carga_venta_carbon'];

            $precioBase = round((float) ($c['precio_base'] ?? 0), 6);
            $descuento = round((float) ($c['descuento'] ?? 0), 6);
            $bono = round((float) ($c['bono'] ?? 0), 6);

            $precioMateriaPrima = round($precioBase - $descuento + $bono, 6);

            $cantidad = (float) DB::table('carga_venta_carbon')
                ->where('id', $idCarga)
                ->value('cantidad_toneladas');

            $subtotal = round($precioMateriaPrima * (float) $cantidad, 2);
            $montoIgv = $porcentajeIgv > 0 ? round($subtotal * ($porcentajeIgv / 100), 2) : 0.0;
            $total = round($subtotal + $montoIgv, 2);
            $montoDetraccion = $porcentajeDetraccion > 0
                ? round($total * ($porcentajeDetraccion / 100), 2)
                : 0.0;
            $netoAPagar = round($total - $montoDetraccion, 2);

            DB::table('carga_venta_carbon')
                ->where('id', $idCarga)
                ->update([
                    'id_liquidacion_venta_carbon' => $idLiquidacion,
                    'precio_base' => $precioBase,
                    'descuento' => $descuento,
                    'bono' => $bono,
                    'precio_materia_prima' => $precioMateriaPrima,
                    'subtotal' => $subtotal,
                    'monto_igv' => $montoIgv,
                    'total' => $total,
                    'monto_detraccion' => $montoDetraccion,
                    'neto_a_pagar' => $netoAPagar,
                    'estado' => EstadoCargaVentaCarbon::EnLiquidacion->value,
                ]);
        }
    }

    /**
     * Recalcula los totales de la liquidacion a partir de sus cargas.
     */
    public static function recalcular_liquidacion(int $id_liquidacion): void
    {
        $totales = DB::table('carga_venta_carbon')
            ->where('id_liquidacion_venta_carbon', $id_liquidacion)
            ->selectRaw('COALESCE(SUM(subtotal), 0) AS subtotal')
            ->selectRaw('COALESCE(SUM(monto_igv), 0) AS monto_igv')
            ->selectRaw('COALESCE(SUM(total), 0) AS total')
            ->selectRaw('COALESCE(SUM(monto_detraccion), 0) AS monto_detraccion')
            ->selectRaw('COALESCE(SUM(neto_a_pagar), 0) AS neto_a_pagar')
            ->first();

        if ($totales === null) {
            return;
        }

        DB::table('liquidacion_venta_carbon')
            ->where('id', $id_liquidacion)
            ->update([
                'subtotal' => round((float) $totales->subtotal, 2),
                'monto_igv' => round((float) $totales->monto_igv, 2),
                'total' => round((float) $totales->total, 2),
                'monto_detraccion' => round((float) $totales->monto_detraccion, 2),
                'neto_a_pagar' => round((float) $totales->neto_a_pagar, 2),
            ]);
    }

    /**
     * Registra el comprobante de una liquidacion confirmada.
     *
     * @param array<string, mixed> $d
     */
    public static function registrar_comprobante(array $d): int
    {
        return DB::transaction(function () use ($d) {
            $idLiquidacion = (int) $d['id_liquidacion_venta_carbon'];

            $liquidacion = DB::table('liquidacion_venta_carbon')
                ->where('id', $idLiquidacion)
                ->lockForUpdate()
                ->first();

            if ($liquidacion === null) {
                throw new \RuntimeException('La liquidacion no existe');
            }

            if ((int) $liquidacion->esta_confirmado !== 1) {
                throw new \RuntimeException(
                    'La liquidacion debe estar confirmada para registrar su comprobante'
                );
            }

            $existente = DB::table('comprobante_venta_carbon')
                ->where('id_liquidacion_venta_carbon', $idLiquidacion)
                ->first();

            if ($existente !== null) {
                throw new \RuntimeException('La liquidacion ya tiene un comprobante registrado');
            }

            $idVenta = (int) $liquidacion->id_venta_carbon;
            $porcentajeIgv = self::porcentaje_igv_venta($idVenta);
            $porcentajeDetraccion = self::porcentaje_detraccion_venta($idVenta);

            $subtotal = round((float) $liquidacion->subtotal, 2);
            // Por ahora no se aplican anticipos: el campo queda en 0.
            $montoAnticipos = 0.0;
            $baseImponible = max(0.0, round($subtotal - $montoAnticipos, 2));
            $montoIgv = $porcentajeIgv > 0 ? round($baseImponible * ($porcentajeIgv / 100), 2) : 0.0;
            $importeTotal = round($baseImponible + $montoIgv, 2);
            $montoDetraccion = $porcentajeDetraccion > 0
                ? round($importeTotal * ($porcentajeDetraccion / 100), 2)
                : 0.0;
            $montoNeto = round($importeTotal - $montoDetraccion, 2);

            return ComprobanteVentaCarbon::insertGetId([
                'id_empleado_registro' => (int) $d['id_empleado_registro'],
                'id_venta_carbon' => $idVenta,
                'id_liquidacion_venta_carbon' => $idLiquidacion,
                'codigo_comprobante' => (string) $d['codigo_comprobante'],
                'fecha_emision' => (string) $d['fecha_emision'],
                'observacion' => isset($d['observacion']) && $d['observacion'] !== ''
                    ? (string) $d['observacion']
                    : null,
                'evidencias' => self::encode_json($d['evidencias'] ?? null),
                'subtotal' => $subtotal,
                'monto_pagado_anticipos' => $montoAnticipos,
                'base_imponible' => $baseImponible,
                'monto_igv' => $montoIgv,
                'importe_total' => $importeTotal,
                'monto_detraccion' => $montoDetraccion,
                'monto_neto' => $montoNeto,
                'avance_pago_neto' => 0.0,
                'avance_pago_detraccion' => 0.0,
                'created_at' => now()->toDateTimeString(),
                'estado' => EstadoComprobanteVentaCarbon::PendienteDePago->value,
            ]);
        });
    }

    /**
     * Registra un pago del cliente sobre un comprobante.
     *
     * @param array<string, mixed> $d
     */
    public static function registrar_pago(array $d): int
    {
        return DB::transaction(function () use ($d) {
            $idComprobante = (int) $d['id_comprobante_venta_carbon'];

            $comprobante = DB::table('comprobante_venta_carbon')
                ->where('id', $idComprobante)
                ->lockForUpdate()
                ->first();

            if ($comprobante === null) {
                throw new \RuntimeException('El comprobante no existe');
            }

            $esDetraccion = !empty($d['es_para_detraccion']);
            $montoPagado = round((float) $d['monto_pagado'], 2);

            $pendiente = $esDetraccion
                ? round((float) $comprobante->monto_detraccion - (float) $comprobante->avance_pago_detraccion, 2)
                : round((float) $comprobante->monto_neto - (float) $comprobante->avance_pago_neto, 2);
            $pendiente = max(0.0, $pendiente);

            if (round($montoPagado - $pendiente, 2) > 0.01) {
                throw new \RuntimeException(
                    'El monto a pagar supera el saldo pendiente del comprobante (S/ '
                    . number_format($pendiente, 2) . ')'
                );
            }

            $idPago = PagoVentaCarbon::insertGetId([
                'id_empleado_registro' => (int) $d['id_empleado_registro'],
                'id_venta_carbon' => (int) $comprobante->id_venta_carbon,
                'id_comprobante_venta_carbon' => $idComprobante,
                'id_cuenta_bancaria_empresa' => isset($d['id_cuenta_bancaria_empresa'])
                    && (int) $d['id_cuenta_bancaria_empresa'] > 0
                        ? (int) $d['id_cuenta_bancaria_empresa']
                        : null,
                'medio_pago' => (string) $d['medio_pago'],
                'numero_operacion' => isset($d['numero_operacion']) && $d['numero_operacion'] !== ''
                    ? (string) $d['numero_operacion']
                    : null,
                'fecha_hora_pago' => (string) $d['fecha_hora_pago'],
                'es_para_detraccion' => $esDetraccion ? 1 : 0,
                'monto_pagado' => $montoPagado,
                'observacion' => isset($d['observacion']) && $d['observacion'] !== ''
                    ? (string) $d['observacion']
                    : null,
                'evidencias' => self::encode_json($d['evidencias'] ?? null),
                'created_at' => now()->toDateTimeString(),
            ]);

            $nuevoAvanceNeto = (float) $comprobante->avance_pago_neto;
            $nuevoAvanceDetraccion = (float) $comprobante->avance_pago_detraccion;

            if ($esDetraccion) {
                $nuevoAvanceDetraccion = round($nuevoAvanceDetraccion + $montoPagado, 2);
            } else {
                $nuevoAvanceNeto = round($nuevoAvanceNeto + $montoPagado, 2);
            }

            $estado = EstadoComprobanteVentaCarbon::resolver(
                totalNeto: (float) $comprobante->monto_neto,
                montoDetraccion: (float) $comprobante->monto_detraccion,
                netoPagado: $nuevoAvanceNeto,
                detraccionPagada: $nuevoAvanceDetraccion
            );

            DB::table('comprobante_venta_carbon')
                ->where('id', $idComprobante)
                ->update([
                    'avance_pago_neto' => $nuevoAvanceNeto,
                    'avance_pago_detraccion' => $nuevoAvanceDetraccion,
                    'estado' => $estado->value,
                ]);

            if ($estado === EstadoComprobanteVentaCarbon::Pagado) {
                DB::table('carga_venta_carbon')
                    ->where('id_liquidacion_venta_carbon', (int) $comprobante->id_liquidacion_venta_carbon)
                    ->update(['estado' => EstadoCargaVentaCarbon::Pagado->value]);
            }

            return $idPago;
        });
    }

    /**
     * Recalcula los importes del comprobante desde los totales de su
     * liquidacion. Se usa cuando se edita una liquidacion que ya tiene
     * comprobante (y todavia no recibio pagos).
     */
    public static function recalcular_comprobante(int $id_comprobante): void
    {
        $comprobante = DB::table('comprobante_venta_carbon')->where('id', $id_comprobante)->first();
        if ($comprobante === null) {
            return;
        }

        $liquidacion = DB::table('liquidacion_venta_carbon')
            ->where('id', (int) $comprobante->id_liquidacion_venta_carbon)
            ->first();

        if ($liquidacion === null) {
            return;
        }

        $idVenta = (int) $comprobante->id_venta_carbon;
        $porcentajeIgv = self::porcentaje_igv_venta($idVenta);
        $porcentajeDetraccion = self::porcentaje_detraccion_venta($idVenta);

        $subtotal = round((float) $liquidacion->subtotal, 2);
        $montoAnticipos = (float) $comprobante->monto_pagado_anticipos;
        $baseImponible = max(0.0, round($subtotal - $montoAnticipos, 2));
        $montoIgv = $porcentajeIgv > 0 ? round($baseImponible * ($porcentajeIgv / 100), 2) : 0.0;
        $importeTotal = round($baseImponible + $montoIgv, 2);
        $montoDetraccion = $porcentajeDetraccion > 0
            ? round($importeTotal * ($porcentajeDetraccion / 100), 2)
            : 0.0;
        $montoNeto = round($importeTotal - $montoDetraccion, 2);

        DB::table('comprobante_venta_carbon')
            ->where('id', $id_comprobante)
            ->update([
                'subtotal' => $subtotal,
                'base_imponible' => $baseImponible,
                'monto_igv' => $montoIgv,
                'importe_total' => $importeTotal,
                'monto_detraccion' => $montoDetraccion,
                'monto_neto' => $montoNeto,
            ]);
    }

    /**
     * Porcentaje de IGV configurado en la venta.
     */
    public static function porcentaje_igv_venta(int $id_venta_carbon): float
    {
        $valor = DB::table('venta_carbon')
            ->where('id', $id_venta_carbon)
            ->value('porcentaje_igv');

        return $valor !== null ? (float) $valor : 0.0;
    }

    /**
     * Porcentaje de detraccion configurado en la venta.
     */
    public static function porcentaje_detraccion_venta(int $id_venta_carbon): float
    {
        $valor = DB::table('venta_carbon')
            ->where('id', $id_venta_carbon)
            ->value('porcentaje_detraccion');

        return $valor !== null ? (float) $valor : 0.0;
    }

    /**
     * Serializa las evidencias a JSON para la columna.
     */
    private static function encode_json(mixed $valor): ?string
    {
        if ($valor === null || $valor === '' || $valor === []) {
            return null;
        }

        if (is_string($valor)) {
            return $valor;
        }

        return json_encode($valor, JSON_UNESCAPED_UNICODE);
    }
}
