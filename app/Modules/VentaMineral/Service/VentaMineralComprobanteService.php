<?php

namespace App\Modules\VentaMineral\Service;

use App\Modules\VentaMineral\Data\VentaMineralAnalisisLiquidacionData;
use App\Modules\VentaMineral\Data\VentaMineralComprobanteData;
use App\Modules\VentaMineral\Data\VentaMineralData;
use App\Modules\VentaMineral\Data\VentaMineralGastoFleteData;
use App\Shared\Enums\VentaMineral\AsumidoPorAnalisis;
use App\Shared\Enums\VentaMineral\EstadoLiquidacionMineral;
use App\Shared\Enums\VentaMineral\MedioPagoDespacho;
use App\Shared\Enums\_Generic\Moneda;
use App\Shared\Helpers\CalculosDespacho;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

/**
 * Orquestacion de los comprobantes de despacho y de sus pagos.
 *
 * Los cuatro tipos comparten la forma del comprobante (subtotal, IGV, total,
 * detraccion y neto) pero cada uno tiene su propia regla para el subtotal:
 * - Flete: toneladas facturadas x precio por tonelada.
 * - Molienda: monto fijo capturado (una sola factura por despacho).
 * - Analisis: suma del costo de servicio de los analisis incluidos.
 * - Liquidacion: los importes de la liquidacion que lo respalda.
 */
class VentaMineralComprobanteService
{
    /** Porcentaje de detraccion por defecto de cada tipo de comprobante. */
    private const DETRACCION_POR_DEFECTO = [
        'flete' => CalculosDespacho::DETRACCION_FLETE_POR_DEFECTO,
        'molienda' => CalculosDespacho::DETRACCION_ESTANDAR_POR_DEFECTO,
        'analisis' => CalculosDespacho::DETRACCION_ESTANDAR_POR_DEFECTO,
        'liquidacion' => CalculosDespacho::DETRACCION_ESTANDAR_POR_DEFECTO,
    ];

    /**
     * Registra un comprobante de despacho.
     *
     * @param array<string, mixed> $payload
     */
    public static function registrar_comprobante(
        string $tipo,
        int $id_despacho_mineral,
        array $payload,
        int $id_empleado
    ): array {
        if (!VentaMineralComprobanteData::tipo_valido($tipo)) {
            return ApiResponse::error('Tipo de comprobante no valido');
        }

        if (VentaMineralData::get_despacho($id_despacho_mineral) === null) {
            return ApiResponse::error('Despacho no encontrado', 404);
        }

        $error = self::validar_cabecera($tipo, $payload);
        if ($error !== null) {
            return ApiResponse::error($error);
        }

        // La molienda se factura una sola vez por despacho: es la factura del
        // molino de destino.
        if ($tipo === 'molienda'
            && VentaMineralComprobanteData::existe_comprobante($tipo, $id_despacho_mineral)
        ) {
            return ApiResponse::error('El despacho ya tiene un comprobante de molienda', 409);
        }

        $calculo = self::calcular_importes($tipo, $id_despacho_mineral, $payload, null);

        if ($calculo['error'] !== null) {
            return ApiResponse::error($calculo['error']);
        }

        $idComprobante = DB::transaction(function () use ($tipo, $id_despacho_mineral, $payload, $id_empleado, $calculo) {
            $id = VentaMineralComprobanteData::registrar_comprobante($tipo, [
                'id_despacho_mineral' => $id_despacho_mineral,
                'id_empleado_registro' => $id_empleado,
                'codigo_comprobante' => (string) $payload['codigo_comprobante'],
                'fecha_emision' => (string) $payload['fecha_emision'],
                'con_detraccion' => $calculo['con_detraccion'],
                'porcentaje_detraccion' => $calculo['porcentaje_detraccion'],
                'porcentaje_igv' => $calculo['porcentaje_igv'],
                'subtotal' => $calculo['subtotal'],
                'igv' => $calculo['igv'],
                'total' => $calculo['total'],
                'monto_detraccion' => $calculo['monto_detraccion'],
                'neto_a_pagar' => $calculo['neto_a_pagar'],
                'evidencias' => $payload['evidencias'] ?? null,
            ] + $calculo['extras']);

            self::asignar_relaciones($tipo, $id, $payload, $id_despacho_mineral);

            return $id;
        });

        // La liquidacion pasa a "Aprobada": ya tiene el comprobante que la
        // respalda.
        if ($tipo === 'liquidacion') {
            $idLiquidacion = (int) ($payload['id_liquidacion_mineral'] ?? 0);
            if ($idLiquidacion > 0) {
                VentaMineralAnalisisLiquidacionData::actualizar_estado_liquidacion(
                    $idLiquidacion,
                    EstadoLiquidacionMineral::Aprobada->value
                );
            }
        }

        $comprobante = VentaMineralComprobanteData::get_comprobante($tipo, $idComprobante);

        return ApiResponse::success($comprobante, 'Comprobante registrado correctamente');
    }

    /**
     * Edita un comprobante de despacho.
     *
     * Si el comprobante ya tiene pagos, los IMPORTES quedan bloqueados: solo
     * se pueden corregir el codigo, la fecha de emision y las evidencias.
     *
     * @param array<string, mixed> $payload
     */
    public static function actualizar_comprobante(
        string $tipo,
        int $id_comprobante,
        array $payload,
        int $id_empleado
    ): array {
        if (!VentaMineralComprobanteData::tipo_valido($tipo)) {
            return ApiResponse::error('Tipo de comprobante no valido');
        }

        $comprobante = VentaMineralComprobanteData::get_comprobante($tipo, $id_comprobante);

        if ($comprobante === null) {
            return ApiResponse::error('Comprobante no encontrado', 404);
        }

        $error = self::validar_cabecera($tipo, $payload);
        if ($error !== null) {
            return ApiResponse::error($error);
        }

        $tienePagos = VentaMineralComprobanteData::contar_pagos($tipo, $id_comprobante) > 0;

        if ($tienePagos) {
            // Solo se actualizan los datos que no mueven el dinero.
            VentaMineralComprobanteData::actualizar_comprobante($tipo, [
                'id_comprobante' => $id_comprobante,
                'codigo_comprobante' => (string) $payload['codigo_comprobante'],
                'fecha_emision' => (string) $payload['fecha_emision'],
                'con_detraccion' => $comprobante->con_detraccion,
                'porcentaje_detraccion' => (float) $comprobante->porcentaje_detraccion,
                'porcentaje_igv' => (float) $comprobante->porcentaje_igv,
                'subtotal' => (float) $comprobante->subtotal,
                'igv' => (float) $comprobante->igv,
                'total' => (float) $comprobante->total,
                'monto_detraccion' => (float) $comprobante->monto_detraccion,
                'neto_a_pagar' => (float) $comprobante->neto_a_pagar,
                'evidencias' => $payload['evidencias'] ?? null,
            ] + self::extras_del_comprobante($tipo, $comprobante));

            $actualizado = VentaMineralComprobanteData::get_comprobante($tipo, $id_comprobante);
            $actualizado->importes_bloqueados = true;

            return ApiResponse::success(
                $actualizado,
                'Comprobante actualizado. Los importes no se modificaron porque ya hay pagos registrados.'
            );
        }

        $calculo = self::calcular_importes($tipo, (int) $comprobante->id_despacho_mineral, $payload, $id_comprobante);

        if ($calculo['error'] !== null) {
            return ApiResponse::error($calculo['error']);
        }

        DB::transaction(function () use ($tipo, $id_comprobante, $payload, $calculo) {
            VentaMineralComprobanteData::actualizar_comprobante($tipo, [
                'id_comprobante' => $id_comprobante,
                'codigo_comprobante' => (string) $payload['codigo_comprobante'],
                'fecha_emision' => (string) $payload['fecha_emision'],
                'con_detraccion' => $calculo['con_detraccion'],
                'porcentaje_detraccion' => $calculo['porcentaje_detraccion'],
                'porcentaje_igv' => $calculo['porcentaje_igv'],
                'subtotal' => $calculo['subtotal'],
                'igv' => $calculo['igv'],
                'total' => $calculo['total'],
                'monto_detraccion' => $calculo['monto_detraccion'],
                'neto_a_pagar' => $calculo['neto_a_pagar'],
                'evidencias' => $payload['evidencias'] ?? null,
            ] + $calculo['extras']);

            self::asignar_relaciones($tipo, $id_comprobante, $payload, (int) $calculo['id_despacho_mineral']);
        });

        $actualizado = VentaMineralComprobanteData::get_comprobante($tipo, $id_comprobante);

        return ApiResponse::success($actualizado, 'Comprobante actualizado correctamente');
    }

    /**
     * Elimina un comprobante de despacho.
     */
    public static function eliminar_comprobante(string $tipo, int $id_comprobante): array
    {
        if (!VentaMineralComprobanteData::tipo_valido($tipo)) {
            return ApiResponse::error('Tipo de comprobante no valido');
        }

        $comprobante = VentaMineralComprobanteData::get_comprobante($tipo, $id_comprobante);

        if ($comprobante === null) {
            return ApiResponse::error('Comprobante no encontrado', 404);
        }

        if (VentaMineralComprobanteData::contar_pagos($tipo, $id_comprobante) > 0) {
            return ApiResponse::error(
                'No se puede eliminar el comprobante: tiene pagos registrados.',
                409
            );
        }

        DB::transaction(function () use ($tipo, $id_comprobante) {
            if ($tipo === 'flete') {
                DB::table('flete_despacho')
                    ->where('id_comprobante_flete_despacho', $id_comprobante)
                    ->update(['id_comprobante_flete_despacho' => null]);
            }

            if ($tipo === 'analisis') {
                DB::table('analisis_laboratorio')
                    ->where('id_comprobante_analisis_despacho', $id_comprobante)
                    ->update(['id_comprobante_analisis_despacho' => null]);
            }

            VentaMineralComprobanteData::eliminar_comprobante($tipo, $id_comprobante);
        });

        // La liquidacion vuelve a "Borrador": ya no tiene comprobante.
        if ($tipo === 'liquidacion' && $comprobante->id_liquidacion_mineral !== null) {
            VentaMineralAnalisisLiquidacionData::actualizar_estado_liquidacion(
                (int) $comprobante->id_liquidacion_mineral,
                EstadoLiquidacionMineral::Borrador->value
            );
        }

        return ApiResponse::success(null, 'Comprobante eliminado correctamente');
    }

    /**
     * Registra un pago sobre un comprobante de despacho.
     *
     * @param array<string, mixed> $payload
     */
    public static function registrar_pago(
        string $tipo,
        int $id_comprobante,
        array $payload,
        int $id_empleado
    ): array {
        if (!VentaMineralComprobanteData::tipo_valido($tipo)) {
            return ApiResponse::error('Tipo de comprobante no valido');
        }

        $comprobante = VentaMineralComprobanteData::get_comprobante($tipo, $id_comprobante);

        if ($comprobante === null) {
            return ApiResponse::error('Comprobante no encontrado', 404);
        }

        $medioPago = MedioPagoDespacho::tryFrom((string) ($payload['medio_pago'] ?? ''));
        if ($medioPago === null) {
            return ApiResponse::error('El medio de pago no es valido');
        }

        $numeroOperacion = trim((string) ($payload['numero_operacion'] ?? ''));
        if ($medioPago->exige_numero_operacion() && $numeroOperacion === '') {
            return ApiResponse::error(
                'El numero de operacion es obligatorio para transferencias y depositos'
            );
        }

        if ((float) ($payload['monto_pagado'] ?? 0) <= 0) {
            return ApiResponse::error('El monto pagado debe ser mayor a 0');
        }

        try {
            VentaMineralComprobanteData::registrar_pago($tipo, [
                'id_comprobante' => $id_comprobante,
                'id_cuenta_bancaria_empresa' => $payload['id_cuenta_bancaria_empresa'] ?? null,
                'id_empleado_registro' => $id_empleado,
                'medio_pago' => $medioPago->value,
                'numero_operacion' => $numeroOperacion,
                'fecha_hora_pago' => (string) $payload['fecha_hora_pago'],
                'es_para_detraccion' => !empty($payload['es_para_detraccion']),
                'monto_pagado' => (float) $payload['monto_pagado'],
                'observacion' => $payload['observacion'] ?? null,
                'evidencias' => $payload['evidencias'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage());
        }

        // La liquidacion refleja el estado de su comprobante.
        if ($tipo === 'liquidacion' && $comprobante->id_liquidacion_mineral !== null) {
            $comprobanteActualizado = VentaMineralComprobanteData::get_comprobante($tipo, $id_comprobante);
            VentaMineralAnalisisLiquidacionData::actualizar_estado_liquidacion(
                (int) $comprobante->id_liquidacion_mineral,
                self::estado_liquidacion_por_comprobante($comprobanteActualizado?->estado)
            );
        }

        $actualizado = VentaMineralComprobanteData::get_comprobante($tipo, $id_comprobante);
        $actualizado->pagos = VentaMineralComprobanteData::get_pagos($tipo, [$id_comprobante])[$id_comprobante] ?? [];

        return ApiResponse::success($actualizado, 'Pago registrado correctamente');
    }

    /**
     * Calcula los importes del comprobante segun su tipo.
     *
     * @param array<string, mixed> $payload
     * @param int|null $id_comprobante Comprobante que se esta editando.
     * @return array{error: string|null, subtotal: float, igv: float, total: float, monto_detraccion: float, neto_a_pagar: float, con_detraccion: bool, porcentaje_detraccion: float, porcentaje_igv: float, extras: array<string, mixed>, id_despacho_mineral: int}
     */
    private static function calcular_importes(
        string $tipo,
        int $id_despacho_mineral,
        array $payload,
        ?int $id_comprobante
    ): array {
        $conDetraccion = !empty($payload['con_detraccion']);

        $porcentajeDetraccion = $conDetraccion
            ? (float) ($payload['porcentaje_detraccion'] ?? self::DETRACCION_POR_DEFECTO[$tipo])
            : 0.0;

        $porcentajeIgv = (float) ($payload['porcentaje_igv'] ?? CalculosDespacho::IGV_POR_DEFECTO);
        $extras = [];
        $subtotal = 0.0;
        $tipoCambio = 1.0;

        if ($tipo === 'flete') {
            $idsFletes = array_map('intval', (array) ($payload['id_fletes'] ?? []));

            if (empty($idsFletes)) {
                return self::calculo_vacio('Debe seleccionar al menos un flete para facturar');
            }

            $error = self::validar_fletes_del_comprobante($idsFletes, $id_despacho_mineral, (int) $payload['id_transportista_mineral'], $id_comprobante);
            if ($error !== null) {
                return self::calculo_vacio($error);
            }

            $pesos = VentaMineralGastoFleteData::sumar_pesos_fletes($idsFletes);

            $toneladas = (float) ($payload['toneladas_cobradas'] ?? 0);
            if ($toneladas <= 0) {
                $toneladas = $pesos['toneladas_neto'] > 0 ? $pesos['toneladas_neto'] : $pesos['toneladas_guia'];
            }

            $precio = (float) ($payload['precio_por_tonelada'] ?? 0);
            $subtotal = CalculosDespacho::subtotal_flete($toneladas, $precio);

            // La moneda del comprobante: en soles el tipo de cambio es 1 y
            // en dolares es obligatorio (ya validado en validar_cabecera).
            $moneda = Moneda::tryFrom((string) ($payload['moneda'] ?? Moneda::PEN->value))
                ?? Moneda::PEN;
            $tipoCambio = $moneda === Moneda::USD
                ? (float) ($payload['tipo_cambio'] ?? 0)
                : 1.0;

            $extras = [
                'id_transportista_mineral' => (int) $payload['id_transportista_mineral'],
                'toneladas_cobradas' => $toneladas,
                'moneda' => $moneda->value,
                'tipo_cambio' => $tipoCambio,
                'precio_por_tonelada' => $precio,
            ];
        } elseif ($tipo === 'molienda') {
            $subtotal = CalculosDespacho::round2((float) ($payload['monto'] ?? 0));
        } elseif ($tipo === 'analisis') {
            $idsAnalisis = array_map('intval', (array) ($payload['id_analisis'] ?? []));

            if (empty($idsAnalisis)) {
                return self::calculo_vacio('Debe seleccionar al menos un analisis para facturar');
            }

            $error = self::validar_analisis_del_comprobante($idsAnalisis, $id_despacho_mineral, $id_comprobante);
            if ($error !== null) {
                return self::calculo_vacio($error);
            }

            // El subtotal del comprobante de analisis es la suma del costo de
            // servicio de los analisis que factura: no se captura a mano.
            $subtotal = VentaMineralAnalisisLiquidacionData::sumar_costo_analisis($idsAnalisis);
        } else {
            $idLiquidacion = (int) ($payload['id_liquidacion_mineral'] ?? 0);
            $liquidacion = VentaMineralAnalisisLiquidacionData::get_liquidacion($idLiquidacion);

            if ($liquidacion === null) {
                return self::calculo_vacio('La liquidacion indicada no existe');
            }

            if ((int) $liquidacion->id_despacho_mineral !== $id_despacho_mineral) {
                return self::calculo_vacio('La liquidacion no pertenece a este despacho');
            }

            $comprobanteExistente = VentaMineralAnalisisLiquidacionData::get_comprobante_de_liquidacion($idLiquidacion);
            if ($comprobanteExistente !== null
                && (int) $comprobanteExistente->id !== (int) ($id_comprobante ?? 0)
            ) {
                return self::calculo_vacio('La liquidacion ya tiene un comprobante registrado');
            }

            // El comprobante COPIA los importes de la liquidacion: nunca
            // discrepa de la liquidacion que lo respalda.
            $subtotal = CalculosDespacho::round2((float) $liquidacion->subtotal);
            $porcentajeIgv = (float) $liquidacion->porcentaje_igv;
            $porcentajeDetraccion = $conDetraccion
                ? (float) $liquidacion->porcentaje_detraccion
                : 0.0;

            // La comprobante de liquidacion es SIEMPRE en dolares: el tipo de
            // cambio dice a cuanto se cobra la detraccion en soles.
            $tipoCambio = (float) ($payload['tipo_cambio'] ?? 1);
            if ($tipoCambio <= 0) {
                return self::calculo_vacio('Debe indicar el tipo de cambio');
            }

            $extras = [
                'id_liquidacion_mineral' => $idLiquidacion,
                'tipo_cambio' => $tipoCambio,
            ];
        }

        $importes = CalculosDespacho::calcular_importes($subtotal, $porcentajeIgv, $porcentajeDetraccion);

        // La detraccion se cobra en soles: si el comprobante esta en dolares,
        // se guarda tambien su equivalente en soles.
        if ($tipo === 'flete' || $tipo === 'liquidacion') {
            $extras['monto_detraccion_soles'] = CalculosDespacho::monto_detraccion_soles(
                $importes['monto_detraccion'],
                $tipoCambio
            );
        }

        return [
            'error' => null,
            'subtotal' => $importes['subtotal'],
            'igv' => $importes['igv'],
            'total' => $importes['total'],
            'monto_detraccion' => $importes['monto_detraccion'],
            'neto_a_pagar' => $importes['neto_a_pagar'],
            'con_detraccion' => $conDetraccion,
            'porcentaje_detraccion' => $porcentajeDetraccion,
            'porcentaje_igv' => $porcentajeIgv,
            'extras' => $extras,
            'id_despacho_mineral' => $id_despacho_mineral,
        ];
    }

    /**
     * Estructura vacia para un calculo fallido.
     *
     * @return array<string, mixed>
     */
    private static function calculo_vacio(string $error): array
    {
        return [
            'error' => $error,
            'subtotal' => 0.0,
            'igv' => 0.0,
            'total' => 0.0,
            'monto_detraccion' => 0.0,
            'neto_a_pagar' => 0.0,
            'con_detraccion' => false,
            'porcentaje_detraccion' => 0.0,
            'porcentaje_igv' => 0.0,
            'extras' => [],
            'id_despacho_mineral' => 0,
        ];
    }

    /**
     * Enlaza los fletes o analisis con el comprobante.
     *
     * @param array<string, mixed> $payload
     */
    private static function asignar_relaciones(
        string $tipo,
        int $id_comprobante,
        array $payload,
        int $id_despacho_mineral
    ): void {
        if ($tipo === 'flete') {
            $idsFletes = array_map('intval', (array) ($payload['id_fletes'] ?? []));

            // Los fletes que salen del comprobante vuelven a quedar libres.
            DB::table('flete_despacho')
                ->where('id_comprobante_flete_despacho', $id_comprobante)
                ->whereNotIn('id', $idsFletes)
                ->update(['id_comprobante_flete_despacho' => null]);

            VentaMineralGastoFleteData::asignar_fletes_comprobante($idsFletes, $id_comprobante);
            return;
        }

        if ($tipo === 'analisis') {
            $idsAnalisis = array_map('intval', (array) ($payload['id_analisis'] ?? []));

            DB::table('analisis_laboratorio')
                ->where('id_comprobante_analisis_despacho', $id_comprobante)
                ->whereNotIn('id', $idsAnalisis)
                ->update(['id_comprobante_analisis_despacho' => null]);

            VentaMineralAnalisisLiquidacionData::asignar_analisis_comprobante($idsAnalisis, $id_comprobante);
        }
    }

    /**
     * Valida la cabecera comun de un comprobante.
     *
     * @param array<string, mixed> $payload
     */
    private static function validar_cabecera(string $tipo, array $payload): ?string
    {
        if (trim((string) ($payload['codigo_comprobante'] ?? '')) === '') {
            return 'Debe indicar el codigo del comprobante';
        }

        if (empty($payload['fecha_emision'])) {
            return 'Debe indicar la fecha de emision del comprobante';
        }

        $porcentajeDetraccion = (float) ($payload['porcentaje_detraccion'] ?? 0);
        if ($porcentajeDetraccion < 0 || $porcentajeDetraccion > 100) {
            return 'El porcentaje de detraccion debe estar entre 0 y 100';
        }

        $porcentajeIgv = (float) ($payload['porcentaje_igv'] ?? CalculosDespacho::IGV_POR_DEFECTO);
        if ($porcentajeIgv < 0 || $porcentajeIgv > 100) {
            return 'El porcentaje de IGV debe estar entre 0 y 100';
        }

        if ($tipo === 'flete') {
            if ((int) ($payload['id_transportista_mineral'] ?? 0) <= 0) {
                return 'Debe indicar el transportista facturado';
            }

            if ((float) ($payload['precio_por_tonelada'] ?? 0) <= 0) {
                return 'Debe indicar el precio por tonelada';
            }

            $moneda = Moneda::tryFrom((string) ($payload['moneda'] ?? Moneda::PEN->value));
            if ($moneda === null) {
                return 'La moneda del comprobante no es valida';
            }

            if ($moneda === Moneda::USD && (float) ($payload['tipo_cambio'] ?? 0) <= 0) {
                return 'Debe indicar el tipo de cambio del comprobante en dolares';
            }
        }

        if ($tipo === 'molienda' && (float) ($payload['monto'] ?? 0) <= 0) {
            return 'Debe indicar el monto de la molienda';
        }

        if ($tipo === 'liquidacion' && (float) ($payload['tipo_cambio'] ?? 1) <= 0) {
            return 'Debe indicar el tipo de cambio';
        }

        return null;
    }

    /**
     * Valida que los fletes pertenezcan al despacho, al transportista indicado
     * y no esten facturados en otro comprobante.
     *
     * @param array<int, int> $ids_fletes
     */
    private static function validar_fletes_del_comprobante(
        array $ids_fletes,
        int $id_despacho_mineral,
        int $id_transportista,
        ?int $id_comprobante
    ): ?string {
        $fletes = VentaMineralGastoFleteData::get_fletes($id_despacho_mineral);
        $porId = [];
        foreach ($fletes as $flete) {
            $porId[(int) $flete->id_flete_despacho] = $flete;
        }

        $vistos = [];

        foreach ($ids_fletes as $idFlete) {
            if (isset($vistos[$idFlete])) {
                return 'El flete esta repetido en el comprobante';
            }
            $vistos[$idFlete] = true;

            $flete = $porId[$idFlete] ?? null;
            if ($flete === null) {
                return 'Uno de los fletes no pertenece a este despacho';
            }

            if ((int) $flete->id_transportista_mineral !== $id_transportista) {
                return 'Todos los fletes deben ser del mismo transportista';
            }

            $comprobanteActual = $flete->id_comprobante_flete_despacho;
            if ($comprobanteActual !== null && (int) $comprobanteActual !== (int) ($id_comprobante ?? 0)) {
                return 'Uno de los fletes ya esta facturado en otro comprobante';
            }
        }

        return null;
    }

    /**
     * Valida que los analisis pertenezcan al despacho, sean propios (los
     * del cliente no se facturan) y no esten facturados en otro comprobante.
     *
     * @param array<int, int> $ids_analisis
     */
    private static function validar_analisis_del_comprobante(
        array $ids_analisis,
        int $id_despacho_mineral,
        ?int $id_comprobante
    ): ?string {
        $analisis = VentaMineralAnalisisLiquidacionData::get_analisis($id_despacho_mineral);
        $porId = [];
        foreach ($analisis as $item) {
            $porId[(int) $item->id_analisis_laboratorio] = $item;
        }

        $vistos = [];

        foreach ($ids_analisis as $idAnalisis) {
            if (isset($vistos[$idAnalisis])) {
                return 'El analisis esta repetido en el comprobante';
            }
            $vistos[$idAnalisis] = true;

            $item = $porId[$idAnalisis] ?? null;
            if ($item === null) {
                return 'Uno de los analisis no pertenece a este despacho';
            }

            if ($item->asumido_por !== AsumidoPorAnalisis::Propio->value) {
                return 'Solo se facturan los analisis asumidos por la empresa';
            }

            $comprobanteActual = $item->id_comprobante_analisis_despacho;
            if ($comprobanteActual !== null && (int) $comprobanteActual !== (int) ($id_comprobante ?? 0)) {
                return 'Uno de los analisis ya esta facturado en otro comprobante';
            }
        }

        return null;
    }

    /**
     * Extras de un comprobante existente, para conservar sus valores al
     * actualizar solo los metadatos.
     *
     * @return array<string, mixed>
     */
    private static function extras_del_comprobante(string $tipo, object $comprobante): array
    {
        if ($tipo === 'flete') {
            $moneda = (string) ($comprobante->moneda ?? Moneda::PEN->value);
            $tipoCambio = (float) ($comprobante->tipo_cambio ?? 1);

            return [
                'id_transportista_mineral' => (int) $comprobante->id_transportista_mineral,
                'toneladas_cobradas' => (float) $comprobante->toneladas_cobradas,
                'moneda' => $moneda,
                'tipo_cambio' => $tipoCambio,
                'precio_por_tonelada' => (float) $comprobante->precio_por_tonelada,
                'monto_detraccion_soles' => CalculosDespacho::monto_detraccion_soles(
                    (float) $comprobante->monto_detraccion,
                    $tipoCambio
                ),
            ];
        }

        if ($tipo === 'liquidacion') {
            $tipoCambio = (float) ($comprobante->tipo_cambio ?? 1);

            return [
                'id_liquidacion_mineral' => (int) $comprobante->id_liquidacion_mineral,
                'tipo_cambio' => $tipoCambio,
                'monto_detraccion_soles' => CalculosDespacho::monto_detraccion_soles(
                    (float) $comprobante->monto_detraccion,
                    $tipoCambio
                ),
            ];
        }

        return [];
    }

    /**
     * Estado de la liquidacion segun el de su comprobante.
     */
    private static function estado_liquidacion_por_comprobante(?string $estadoComprobante): string
    {
        return \App\Shared\Enums\VentaMineral\EstadoLiquidacionMineral::desdeComprobante($estadoComprobante)->value;
    }
}
