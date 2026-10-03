<?php

namespace App\Modules\CompraCarbon\Service;

use App\Modules\CompraCarbon\Data\CompraCarbonData;
use App\Modules\CompraCarbon\Data\CompraCarbonPagosData;
use App\Shared\Enums\CompraCarbon\EstadoCompraCarbon;
use App\Shared\Enums\CompraCarbon\EstadoComprobanteCarbon;
use App\Shared\Enums\CompraCarbon\MedioPago;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

/**
 * Logica de negocio de los comprobantes y pagos de Compra de Carbon.
 *
 * Los montos (`total`, `total_neto`, `monto_detraccion`) NUNCA se reciben del
 * cliente: se derivan del `total_con_descuento` de la compra o de la suma de
 * `descuento_flete` de las cargas, para que el saldo nunca pueda descuadrarse
 * del documento real.
 */
class CompraCarbonPagosService
{
    /** Porcentaje por defecto de detraccion al proveedor (SUNAT). */
    public const DETRACCION_PROVEEDOR_DEFECTO = 10.0;

    /** Porcentaje por defecto de detraccion al transportista. */
    public const DETRACCION_TRANSPORTE_DEFECTO = 4.0;

    /** Tope maximo aceptable de porcentaje de detraccion. */
    public const DETRACCION_MAXIMA = 30.0;

    private const CARPETA_COMPROBANTES = 'comprobantes-compra-carbon';
    private const CARPETA_PAGOS = 'pagos-compra-carbon';

    // ==================================================================
    // LECTURAS
    // ==================================================================

    /**
     * Comprobante del proveedor de una compra, con sus pagos.
     */
    public static function get_comprobante_proveedor(int $id_compra_carbon): array
    {
        $compra = self::obtener_compra($id_compra_carbon);
        if ($compra === null) {
            return ApiResponse::error('La compra no existe');
        }

        $comprobante = CompraCarbonPagosData::get_comprobante_proveedor($id_compra_carbon);
        if ($comprobante === null) {
            return ApiResponse::success(null, 'La compra no tiene comprobante registrado');
        }

        $comprobante->pagos = CompraCarbonPagosData::get_pagos_comprobante_proveedor((int) $comprobante->id_comprobante_compra_carbon);

        return ApiResponse::success($comprobante, 'Comprobante obtenido correctamente');
    }

    /**
     * Grupos de flete por transportista pendientes de comprobante.
     */
    public static function get_grupos_flete(int $id_compra_carbon): array
    {
        $compra = self::obtener_compra($id_compra_carbon);
        if ($compra === null) {
            return ApiResponse::error('La compra no existe');
        }

        $grupos = CompraCarbonPagosData::get_grupos_flete($id_compra_carbon);
        foreach ($grupos as $grupo) {
            $grupo->porcentaje_detraccion_defecto = self::DETRACCION_TRANSPORTE_DEFECTO;
        }

        return ApiResponse::success($grupos, 'Grupos de flete obtenidos correctamente');
    }

    /**
     * Comprobante de flete de un transportista con sus cargas y pagos.
     */
    public static function get_comprobante_transporte(int $id_compra_carbon, int $id_comprobante): array
    {
        $compra = self::obtener_compra($id_compra_carbon);
        if ($compra === null) {
            return ApiResponse::error('La compra no existe');
        }

        $comprobante = CompraCarbonPagosData::get_comprobante_transporte_por_id($id_comprobante);
        if ($comprobante === null || (int) $comprobante->id_compra_carbon !== $id_compra_carbon) {
            return ApiResponse::error('El comprobante de flete no existe para esta compra');
        }

        $comprobante->cargas = CompraCarbonPagosData::get_cargas_comprobante_transporte($id_comprobante);
        $comprobante->pagos = CompraCarbonPagosData::get_pagos_comprobante_transporte($id_comprobante);
        $comprobante->porcentaje_detraccion_defecto = self::DETRACCION_TRANSPORTE_DEFECTO;

        return ApiResponse::success($comprobante, 'Comprobante de flete obtenido correctamente');
    }

    /**
     * Historial completo de pagos de una compra: proveedor y transportista.
     */
    public static function get_pagos(int $id_compra_carbon): array
    {
        $compra = self::obtener_compra($id_compra_carbon);
        if ($compra === null) {
            return ApiResponse::error('La compra no existe');
        }

        $comprobanteProveedor = CompraCarbonPagosData::get_comprobante_proveedor($id_compra_carbon);
        $saldos = CompraCarbonPagosData::get_saldos_compra($id_compra_carbon);

        $comprobantes = [];
        if ($comprobanteProveedor !== null) {
            $comprobanteProveedor->pagos = CompraCarbonPagosData::get_pagos_comprobante_proveedor((int) $comprobanteProveedor->id_comprobante_compra_carbon);
            $comprobantes[] = $comprobanteProveedor;
        }
        foreach (CompraCarbonPagosData::get_comprobantes_transporte($id_compra_carbon) as $ct) {
            $ct->cargas = CompraCarbonPagosData::get_cargas_comprobante_transporte((int) $ct->id_comprobante_transporte_carbon);
            $ct->pagos = CompraCarbonPagosData::get_pagos_comprobante_transporte((int) $ct->id_comprobante_transporte_carbon);
            $comprobantes[] = $ct;
        }

        return ApiResponse::success([
            'compras' => [
                'aplica_igv' => (int) $compra->aplica_igv === 1,
                'total_con_descuento' => $saldos['total_con_descuento'],
                'monto_pagado_anticipos' => $saldos['monto_pagado_anticipos'],
                'avance_pago_neto' => $saldos['avance_pago_neto'],
                'descuento_flete' => $saldos['descuento_flete'],
                'avance_pago_flete' => $saldos['avance_pago_flete'],
            ],
            'grupos_flete' => CompraCarbonPagosData::get_grupos_flete($id_compra_carbon),
            'comprobantes' => $comprobantes,
            'pagos_proveedor' => CompraCarbonPagosData::get_pagos_proveedor($id_compra_carbon),
            'pagos_transporte' => CompraCarbonPagosData::get_pagos_transporte($id_compra_carbon),
        ], 'Pagos obtenidos correctamente');
    }

    // ==================================================================
    // ALTA DE COMPROBANTES
    // ==================================================================

    /**
     * Registra el comprobante que el proveedor entrega por la compra.
     *
     * Solo existe si la compra aplica IGV: si no lo aplica, el proveedor esta
     * vendiendo sin comprobante y no hay documento que registrar.
     *
     * @param array<string, mixed> $payload
     * @param array<int, \Illuminate\Http\UploadedFile> $archivos
     */
    public static function registrar_comprobante_proveedor(int $id_compra_carbon, array $payload, int $id_empleado, array $archivos): array
    {
        $compra = self::obtener_compra($id_compra_carbon);
        if ($compra === null) {
            return ApiResponse::error('La compra no existe');
        }

        $errorEstado = self::validar_estado_pagable($compra);
        if ($errorEstado !== null) {
            return ApiResponse::error($errorEstado);
        }

        if ((int) $compra->aplica_igv !== 1) {
            return ApiResponse::error('Esta compra no aplica IGV, por lo que no corresponde registrar un comprobante. Los pagos se registran directamente.');
        }

        if (CompraCarbonPagosData::get_comprobante_proveedor($id_compra_carbon) !== null) {
            return ApiResponse::error('Esta compra ya tiene un comprobante registrado');
        }

        $total = round((float) $compra->total_con_descuento, 2);

        $errores = self::validar_datos_comprobante($payload, $total, self::DETRACCION_PROVEEDOR_DEFECTO);
        if ($errores !== null) {
            return ApiResponse::error($errores);
        }

        $evidencias = self::persistir_evidencias($archivos, self::CARPETA_COMPROBANTES);
        if ($evidencias['error'] !== null) {
            return ApiResponse::error($evidencias['error']);
        }

        $desglose = self::calcular_detraccion(
            $total,
            (float) $payload['porcentaje_detraccion'],
            $payload['con_detraccion']
        );

        CompraCarbonPagosData::insert_comprobante_proveedor([
            'id_empleado_registro' => $id_empleado,
            'id_compra_carbon' => $id_compra_carbon,
            'codigo_comprobante' => $payload['codigo_comprobante'],
            'fecha_emision' => $payload['fecha_emision'],
            'observacion' => $payload['observacion'] ?? null,
            'evidencias' => $evidencias['json'],
            'total' => $total,
            'con_detraccion' => $payload['con_detraccion'],
            'porcentaje_detraccion' => $desglose['porcentaje'],
            'monto_detraccion' => $desglose['monto_detraccion'],
            'total_neto' => $desglose['total_neto'],
            'created_at' => now()->toDateTimeString(),
            'estado' => EstadoComprobanteCarbon::PendienteDePago->value,
        ]);

        return self::get_comprobante_proveedor($id_compra_carbon);
    }

    /**
     * Registra el comprobante de flete de un transportista.
     *
     * El total NO se recibe del cliente: es la suma de `descuento_flete` de
     * las cargas indicadas, ya validadas como pertenecientes a la compra y
     * marcadas con `pagar_flete`.
     *
     * @param array<string, mixed> $payload
     * @param array<int, \Illuminate\Http\UploadedFile> $archivos
     */
    public static function registrar_comprobante_transporte(int $id_compra_carbon, array $payload, int $id_empleado, array $archivos): array
    {
        $compra = self::obtener_compra($id_compra_carbon);
        if ($compra === null) {
            return ApiResponse::error('La compra no existe');
        }

        $errorEstado = self::validar_estado_pagable($compra);
        if ($errorEstado !== null) {
            return ApiResponse::error($errorEstado);
        }

        $idTransportista = (int) ($payload['id_transportista'] ?? 0);
        if ($idTransportista <= 0) {
            return ApiResponse::error('Debe indicar el transportista del comprobante');
        }

        $existente = CompraCarbonPagosData::get_comprobante_transporte_de_transportista($id_compra_carbon, $idTransportista);
        if ($existente !== null) {
            return ApiResponse::error('Ya existe un comprobante de flete registrado para este transportista');
        }

        $idsRecibidos = array_values(array_unique(array_map('intval', (array) ($payload['ids_detalle_carga'] ?? []))));
        $idsValidos = CompraCarbonPagosData::filtrar_detalles_con_flete($id_compra_carbon, $idsRecibidos);

        if (empty($idsValidos)) {
            return ApiResponse::error('No hay cargas válidas con pago de flete para este transportista');
        }
        if (count($idsValidos) !== count($idsRecibidos)) {
            return ApiResponse::error('Algunas de las cargas indicadas no tienen pago de flete o no pertenecen a esta compra');
        }

        $cargas = CompraCarbonPagosData::get_cargas_a_facturar($id_compra_carbon, $idTransportista, $idsValidos);

        $total = round(array_sum(array_map(fn ($c): float => (float) $c->descuento_flete, $cargas)), 2);
        if ($total <= 0) {
            return ApiResponse::error('El total del comprobante de flete debe ser mayor a 0');
        }

        $errores = self::validar_datos_comprobante($payload, $total, self::DETRACCION_TRANSPORTE_DEFECTO);
        if ($errores !== null) {
            return ApiResponse::error($errores);
        }

        $evidencias = self::persistir_evidencias($archivos, self::CARPETA_COMPROBANTES);
        if ($evidencias['error'] !== null) {
            return ApiResponse::error($evidencias['error']);
        }

        $desglose = self::calcular_detraccion($total, (float) $payload['porcentaje_detraccion'], $payload['con_detraccion']);

        $idComprobante = CompraCarbonPagosData::insert_comprobante_transporte([
            'id_compra_carbon' => $id_compra_carbon,
            'id_empleado_registro' => $id_empleado,
            'id_transportista' => $idTransportista,
            'codigo_comprobante' => $payload['codigo_comprobante'],
            'fecha_emision' => $payload['fecha_emision'],
            'observacion' => $payload['observacion'] ?? null,
            'evidencias' => $evidencias['json'],
            'total' => $total,
            'con_detraccion' => $payload['con_detraccion'],
            'porcentaje_detraccion' => $desglose['porcentaje'],
            'monto_detraccion' => $desglose['monto_detraccion'],
            'total_neto' => $desglose['total_neto'],
            'created_at' => now()->toDateTimeString(),
            'estado' => EstadoComprobanteCarbon::PendienteDePago->value,
        ], $idsValidos);

        return self::get_comprobante_transporte($id_compra_carbon, $idComprobante);
    }

    // ==================================================================
    // ALTA DE PAGOS
    // ==================================================================

    /**
     * Registra un pago al proveedor.
     *
     * Si la compra aplica IGV el pago va enlazado a su comprobante; si no, el
     * pago es integro y queda solo colgando de la compra.
     *
     * @param array<string, mixed> $payload
     * @param array<int, \Illuminate\Http\UploadedFile> $archivos
     */
    public static function registrar_pago_proveedor(int $id_compra_carbon, array $payload, int $id_empleado, array $archivos): array
    {
        $compra = self::obtener_compra($id_compra_carbon);
        if ($compra === null) {
            return ApiResponse::error('La compra no existe');
        }

        $errorEstado = self::validar_estado_pagable($compra);
        if ($errorEstado !== null) {
            return ApiResponse::error($errorEstado);
        }

        $aplicaIgv = (int) $compra->aplica_igv === 1;
        $comprobante = CompraCarbonPagosData::get_comprobante_proveedor($id_compra_carbon);
        $idComprobante = null;

        if ($aplicaIgv) {
            if ($comprobante === null) {
                return ApiResponse::error('Esta compra aplica IGV pero aun no tiene comprobante registrado');
            }
            $idComprobante = (int) $comprobante->id_comprobante_compra_carbon;
        } elseif ($comprobante !== null) {
            return ApiResponse::error('Esta compra no aplica IGV, el pago se registra sin comprobante');
        }

        $esParaDetraccion = !empty($payload['es_para_detraccion']);
        if ($esParaDetraccion && ($comprobante === null || !$comprobante->con_detraccion)) {
            return ApiResponse::error('Este comprobante no tiene detraccion, no se pueden registrar pagos de detraccion');
        }

        $errorPago = self::validar_datos_pago($payload);
        if ($errorPago !== null) {
            return ApiResponse::error($errorPago);
        }

        $errorCuentaEmpresa = CompraCarbonPagosData::validar_cuenta(
            'cuenta_bancaria_empresa',
            'id_empresa',
            (int) $payload['id_cuenta_bancaria_empresa'],
            (int) $compra->id_empresa,
            null
        );
        if (!$errorCuentaEmpresa['ok']) {
            return ApiResponse::error($errorCuentaEmpresa['mensaje']);
        }

        $errorCuentaDestino = CompraCarbonPagosData::validar_cuenta(
            'cuenta_bancaria_proveedor',
            'id_proveedor',
            (int) $payload['id_cuenta_bancaria_proveedor'],
            (int) $compra->id_proveedor,
            $esParaDetraccion
        );
        if (!$errorCuentaDestino['ok']) {
            return ApiResponse::error($errorCuentaDestino['mensaje']);
        }

        $saldos = CompraCarbonPagosData::get_saldos_compra($id_compra_carbon);
        $saldoBucket = self::saldo_bucket_proveedor($saldos, $idComprobante, $comprobante, $esParaDetraccion);

        if ($saldoBucket['disponible'] < (float) $payload['monto_pagado'] - 0.01) {
            return ApiResponse::error(
                "El monto a pagar supera el saldo disponible de " . self::etiqueta_bucket($esParaDetraccion)
                . " (disponible: {$saldoBucket['disponible']}, monto: {$payload['monto_pagado']})"
            );
        }

        $evidencias = self::persistir_evidencias($archivos, self::CARPETA_PAGOS);
        if ($evidencias['error'] !== null) {
            return ApiResponse::error($evidencias['error']);
        }

        return DB::transaction(function () use (
            $id_compra_carbon,
            $idComprobante,
            $payload,
            $id_empleado,
            $evidencias,
            $esParaDetraccion
        ) {
            CompraCarbonPagosData::insert_pago_proveedor([
                'id_compra_carbon' => $id_compra_carbon,
                'id_comprobante_compra_carbon' => $idComprobante,
                'id_cuenta_bancaria_empresa' => (int) $payload['id_cuenta_bancaria_empresa'],
                'id_cuenta_bancaria_proveedor' => (int) $payload['id_cuenta_bancaria_proveedor'],
                'id_empleado_registro' => $id_empleado,
                'medio_pago' => $payload['medio_pago'],
                'numero_operacion' => $payload['numero_operacion'] ?? null,
                'fecha_hora_pago' => $payload['fecha_hora_pago'],
                'es_para_detraccion' => $esParaDetraccion,
                'observacion' => $payload['observacion'] ?? null,
                'evidencias' => $evidencias['json'],
                'monto_pagado' => (float) $payload['monto_pagado'],
                'created_at' => now()->toDateTimeString(),
            ]);

            // El avance del neto de la compra solo crece con pagos no-detraccion.
            CompraCarbonPagosData::sumar_avance_compra(
                $id_compra_carbon,
                $esParaDetraccion ? 0.0 : (float) $payload['monto_pagado'],
                0.0
            );

            if ($idComprobante !== null) {
                self::sincronizar_comprobante_proveedor($idComprobante);
            }

            self::sincronizar_estado_compra($id_compra_carbon);

            return self::get_pagos($id_compra_carbon);
        });
    }

    /**
     * Registra un pago al transportista.
     *
     * El flete siempre viaja con comprobante: el transportista emite factura
     * por el servicio, asi que no existe el caso de pago directo sin documento.
     *
     * @param array<string, mixed> $payload
     * @param array<int, \Illuminate\Http\UploadedFile> $archivos
     */
    public static function registrar_pago_transporte(int $id_compra_carbon, array $payload, int $id_empleado, array $archivos): array
    {
        $compra = self::obtener_compra($id_compra_carbon);
        if ($compra === null) {
            return ApiResponse::error('La compra no existe');
        }

        $errorEstado = self::validar_estado_pagable($compra);
        if ($errorEstado !== null) {
            return ApiResponse::error($errorEstado);
        }

        $idComprobante = (int) ($payload['id_comprobante_transporte_carbon'] ?? 0);
        if ($idComprobante <= 0) {
            return ApiResponse::error('Debe indicar el comprobante de flete al que corresponde el pago');
        }

        $comprobante = CompraCarbonPagosData::get_comprobante_transporte_por_id($idComprobante);
        if ($comprobante === null || (int) $comprobante->id_compra_carbon !== $id_compra_carbon) {
            return ApiResponse::error('El comprobante de flete no existe para esta compra');
        }

        $esParaDetraccion = !empty($payload['es_para_detraccion']);
        if ($esParaDetraccion && !$comprobante->con_detraccion) {
            return ApiResponse::error('Este comprobante no tiene detraccion, no se pueden registrar pagos de detraccion');
        }

        $errorPago = self::validar_datos_pago($payload);
        if ($errorPago !== null) {
            return ApiResponse::error($errorPago);
        }

        $errorCuentaEmpresa = CompraCarbonPagosData::validar_cuenta(
            'cuenta_bancaria_empresa',
            'id_empresa',
            (int) $payload['id_cuenta_bancaria_empresa'],
            (int) $compra->id_empresa,
            null
        );
        if (!$errorCuentaEmpresa['ok']) {
            return ApiResponse::error($errorCuentaEmpresa['mensaje']);
        }

        $errorCuentaTransportista = CompraCarbonPagosData::validar_cuenta(
            'cuenta_bancaria_transportista',
            'id_transportista',
            (int) $payload['id_cuenta_bancaria_transportista'],
            (int) $comprobante->id_transportista,
            $esParaDetraccion
        );
        if (!$errorCuentaTransportista['ok']) {
            return ApiResponse::error($errorCuentaTransportista['mensaje']);
        }

        $avances = CompraCarbonPagosData::get_avances_comprobante_transporte($idComprobante);
        $disponible = $esParaDetraccion
            ? round((float) $comprobante->monto_detraccion - $avances['detraccion'], 2)
            : round((float) $comprobante->total_neto - $avances['neto'], 2);

        if ($disponible < (float) $payload['monto_pagado'] - 0.01) {
            return ApiResponse::error(
                "El monto a pagar supera el saldo disponible de " . self::etiqueta_bucket($esParaDetraccion)
                . " (disponible: {$disponible}, monto: {$payload['monto_pagado']})"
            );
        }

        $evidencias = self::persistir_evidencias($archivos, self::CARPETA_PAGOS);
        if ($evidencias['error'] !== null) {
            return ApiResponse::error($evidencias['error']);
        }

        return DB::transaction(function () use (
            $id_compra_carbon,
            $idComprobante,
            $payload,
            $id_empleado,
            $evidencias,
            $esParaDetraccion
        ) {
            CompraCarbonPagosData::insert_pago_transporte([
                'id_compra_carbon' => $id_compra_carbon,
                'id_comprobante_transporte_carbon' => $idComprobante,
                'id_cuenta_bancaria_empresa' => (int) $payload['id_cuenta_bancaria_empresa'],
                'id_cuenta_bancaria_transportista' => (int) $payload['id_cuenta_bancaria_transportista'],
                'id_empleado_registro' => $id_empleado,
                'medio_pago' => $payload['medio_pago'],
                'numero_operacion' => $payload['numero_operacion'] ?? null,
                'fecha_hora_pago' => $payload['fecha_hora_pago'],
                'es_para_detraccion' => $esParaDetraccion,
                'observacion' => $payload['observacion'] ?? null,
                'evidencias' => $evidencias['json'],
                'monto_pagado' => (float) $payload['monto_pagado'],
                'created_at' => now()->toDateTimeString(),
            ]);

            CompraCarbonPagosData::sumar_avance_compra(
                $id_compra_carbon,
                0.0,
                $esParaDetraccion ? 0.0 : (float) $payload['monto_pagado']
            );

            self::sincronizar_comprobante_transporte($idComprobante);
            self::sincronizar_estado_compra($id_compra_carbon);

            return self::get_pagos($id_compra_carbon);
        });
    }

    // ==================================================================
    // HELPERS
    // ==================================================================

    /**
     * @return object|null
     */
    private static function obtener_compra(int $id_compra_carbon): ?object
    {
        $resultado = CompraCarbonData::get_compra_con_detalles($id_compra_carbon);

        return $resultado['cabecera'];
    }

    /**
     * Las compras solo admiten comprobantes y pagos desde que su liquidacion
     * fue aprobada, y mientras no esten anuladas o ya saldadas por completo.
     */
    private static function validar_estado_pagable(object $compra): ?string
    {
        $estado = (string) $compra->estado;

        if ($estado === EstadoCompraCarbon::Anulado->value) {
            return 'La compra esta anulada, no admite comprobantes ni pagos';
        }
        if (in_array($estado, [
            EstadoCompraCarbon::Preliminar->value,
            EstadoCompraCarbon::Confirmado->value,
        ], true)) {
            return 'Solo se pueden registrar comprobantes y pagos cuando la liquidacion de la compra fue aprobada';
        }
        if ($estado === EstadoCompraCarbon::Pagado->value) {
            return 'La compra ya se encuentra pagada en su totalidad';
        }

        return null;
    }

    /**
     * Valida los campos del comprobante y NORMALIZA en `$payload` los
     * opcionales (tipo de comprobante como string del enum, fecha recortada,
     * observacion vacia a null, porcentaje forzado a 0 sin detraccion).
     * Se pasa por referencia a proposito: el llamador necesita esas
     * normalizaciones para persistir.
     *
     * @param array<string, mixed> $payload
     * @return string|null Mensaje de error, o null si todo esta bien.
     */
    private static function validar_datos_comprobante(array &$payload, float $total, float $porcentajeDefecto): ?string
    {
        $codigo = trim((string) ($payload['codigo_comprobante'] ?? ''));
        if ($codigo === '') {
            return 'El codigo del comprobante es obligatorio';
        }
        $payload['codigo_comprobante'] = $codigo;

        $fechaEmision = trim((string) ($payload['fecha_emision'] ?? ''));
        if ($fechaEmision === '') {
            return 'La fecha de emision del comprobante es obligatoria';
        }
        $payload['fecha_emision'] = $fechaEmision;

        $payload['con_detraccion'] = !empty($payload['con_detraccion']);
        $porcentaje = $payload['con_detraccion']
            ? (float) ($payload['porcentaje_detraccion'] ?? $porcentajeDefecto)
            : 0.0;

        if ($payload['con_detraccion'] && ($porcentaje < 0 || $porcentaje > self::DETRACCION_MAXIMA)) {
            return 'El porcentaje de detraccion debe estar entre 0 y ' . self::DETRACCION_MAXIMA;
        }
        $payload['porcentaje_detraccion'] = round($porcentaje, 2);

        $observacion = trim((string) ($payload['observacion'] ?? ''));
        $payload['observacion'] = $observacion === '' ? null : $observacion;

        if ($total <= 0) {
            return 'El total del comprobante debe ser mayor a 0';
        }

        return null;
    }

    /**
     * Valida y NORMALIZA en `$payload` los campos de un pago.
     * Se pasa por referencia a proposito, ver `validar_datos_comprobante`.
     *
     * @param array<string, mixed> $payload
     * @return string|null Mensaje de error, o null si todo esta bien.
     */
    private static function validar_datos_pago(array &$payload): ?string
    {
        $medio = MedioPago::tryFrom((string) ($payload['medio_pago'] ?? ''));
        if ($medio === null) {
            return 'El medio de pago debe ser Transferencia, Deposito o Efectivo';
        }
        $payload['medio_pago'] = $medio->value;

        $numeroOperacion = trim((string) ($payload['numero_operacion'] ?? ''));
        if ($numeroOperacion === '') {
            if ($medio->exige_numero_operacion()) {
                return "El numero de operacion es obligatorio cuando el medio de pago es {$medio->value}";
            }
            $payload['numero_operacion'] = null;
        } else {
            $payload['numero_operacion'] = $numeroOperacion;
        }

        $fechaHoraPago = trim((string) ($payload['fecha_hora_pago'] ?? ''));
        if ($fechaHoraPago === '') {
            return 'La fecha y hora del pago son obligatorias';
        }
        $payload['fecha_hora_pago'] = $fechaHoraPago;

        $monto = (float) ($payload['monto_pagado'] ?? 0);
        if ($monto <= 0) {
            return 'El monto pagado debe ser mayor a 0';
        }
        $payload['monto_pagado'] = round($monto, 2);

        if ((int) ($payload['id_cuenta_bancaria_empresa'] ?? 0) <= 0) {
            return 'Debe indicar la cuenta bancaria de la empresa de donde sale el dinero';
        }

        $observacion = trim((string) ($payload['observacion'] ?? ''));
        $payload['observacion'] = $observacion === '' ? null : $observacion;

        return null;
    }

    /**
     * Deriva los montos del comprobante a partir del total y la detraccion.
     *
     * @return array{porcentaje: float, monto_detraccion: float, total_neto: float}
     */
    private static function calcular_detraccion(float $total, float $porcentaje, bool $conDetraccion): array
    {
        if (!$conDetraccion) {
            return ['porcentaje' => 0.0, 'monto_detraccion' => 0.0, 'total_neto' => round($total, 2)];
        }

        $monto = round($total * $porcentaje / 100, 2);

        return [
            'porcentaje' => round($porcentaje, 2),
            'monto_detraccion' => $monto,
            'total_neto' => round($total - $monto, 2),
        ];
    }

    /**
     * Persiste los adjuntos. Si llegan archivos pero ninguno se pudo guardar,
     * devuelve error para abortar y no perder en silencio lo que el usuario adjunto.
     *
     * @param array<int, \Illuminate\Http\UploadedFile> $archivos
     * @return array{json: string|null, error: string|null}
     */
    private static function persistir_evidencias(array $archivos, string $carpeta): array
    {
        if (count($archivos) === 0) {
            return ['json' => null, 'error' => null];
        }

        $guardadas = ArchivoHelper::guardarArchivos($carpeta, $archivos);
        if (count($guardadas) === 0) {
            return ['json' => null, 'error' => 'No se pudieron guardar las evidencias'];
        }

        return ['json' => json_encode($guardadas, JSON_UNESCAPED_UNICODE), 'error' => null];
    }

    /**
     * Recalcula el estado de un comprobante del proveedor tras un pago.
     */
    private static function sincronizar_comprobante_proveedor(int $id_comprobante): void
    {
        $comprobante = CompraCarbonPagosData::get_comprobante_proveedor_por_id($id_comprobante);
        if ($comprobante === null) {
            return;
        }

        $avances = CompraCarbonPagosData::get_avances_comprobante_proveedor($id_comprobante);
        $estado = EstadoComprobanteCarbon::resolver(
            (float) $comprobante->total_neto,
            (float) $comprobante->monto_detraccion,
            $avances['neto'],
            $avances['detraccion']
        );

        CompraCarbonPagosData::set_estado_comprobante_proveedor($id_comprobante, $estado->value, $avances['detraccion']);
    }

    /**
     * Recalcula el estado de un comprobante de flete tras un pago.
     */
    private static function sincronizar_comprobante_transporte(int $id_comprobante): void
    {
        $comprobante = CompraCarbonPagosData::get_comprobante_transporte_por_id($id_comprobante);
        if ($comprobante === null) {
            return;
        }

        $avances = CompraCarbonPagosData::get_avances_comprobante_transporte($id_comprobante);
        $estado = EstadoComprobanteCarbon::resolver(
            (float) $comprobante->total_neto,
            (float) $comprobante->monto_detraccion,
            $avances['neto'],
            $avances['detraccion']
        );

        CompraCarbonPagosData::set_estado_comprobante_transporte($id_comprobante, $estado->value, $avances['detraccion']);
    }

    /**
     * Reevalua el estado global de la compra a partir de sus saldos.
     *
     * Se decide sobre el saldo de CADA comprobante (neto y detraccion por
     * separado) y no sobre `avance_pago_neto` / `avance_pago_flete`: esos
     * acumulados solo crecen con los pagos no-detraccion, asi que el monto
     * retenido se leeria como saldo pendiente y la compra nunca cerraria aun
     * estando pagada de verdad.
     */
    private static function sincronizar_estado_compra(int $id_compra_carbon): void
    {
        $saldos = CompraCarbonPagosData::get_saldos_por_comprobante($id_compra_carbon);

        $hayPagos = false;
        $todoCubierto = true;

        foreach ($saldos as $saldo) {
            if ($saldo['neto_pagado'] > 0 || $saldo['detraccion_pagada'] > 0) {
                $hayPagos = true;
            }
            if ($saldo['saldo_total'] > 0.01) {
                $todoCubierto = false;
            }
        }

        if (!$hayPagos) {
            return;
        }

        CompraCarbonPagosData::set_estado_compra(
            $id_compra_carbon,
            $todoCubierto
                ? EstadoCompraCarbon::Pagado->value
                : EstadoCompraCarbon::EnProcesoPago->value
        );
    }

    /**
     * Saldo que le queda al bucket (neto o detraccion) que se esta pagando.
     *
     * @param array{saldos_pagos_proveedor: array<object>, ...} $saldos
     * @param object|null $comprobante
     * @return array{disponible: float}
     */
    private static function saldo_bucket_proveedor(
        array $saldos,
        ?int $idComprobante,
        ?object $comprobante,
        bool $esParaDetraccion
    ): array {
        // Compra sin IGV: todo el pago es neto y compite con los anticipos.
        if ($idComprobante === null || $comprobante === null) {
            $pagadoSinComprobante = 0.0;
            foreach ($saldos['saldos_pagos_proveedor'] as $grupo) {
                if ((int) $grupo->id_comprobante === 0) {
                    $pagadoSinComprobante = (float) $grupo->neto;
                }
            }

            return [
                'disponible' => round(
                    $saldos['total_con_descuento'] - $saldos['monto_pagado_anticipos'] - $pagadoSinComprobante,
                    2
                ),
            ];
        }

        $avances = CompraCarbonPagosData::get_avances_comprobante_proveedor($idComprobante);

        return [
            'disponible' => $esParaDetraccion
                ? round((float) $comprobante->monto_detraccion - $avances['detraccion'], 2)
                : round((float) $comprobante->total_neto - $avances['neto'], 2),
        ];
    }

    private static function etiqueta_bucket(bool $esParaDetraccion): string
    {
        return $esParaDetraccion ? 'la detracción' : 'la compra';
    }
}
