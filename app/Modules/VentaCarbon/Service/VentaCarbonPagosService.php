<?php

namespace App\Modules\VentaCarbon\Service;

use App\Modules\VentaCarbon\Data\VentaCarbonData;
use App\Modules\VentaCarbon\Data\VentaCarbonPagosData;
use App\Shared\Enums\VentaCarbon\EstadoCargaVentaCarbon;
use App\Shared\Enums\VentaCarbon\EstadoLiquidacionVentaCarbon;
use App\Shared\Enums\VentaCarbon\MedioPagoVentaCarbon;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\UploadedFile;

/**
 * Orquestacion de la cadena de facturacion de la venta de carbon:
 * Liquidacion -> Comprobante -> Pago.
 */
class VentaCarbonPagosService
{
    private const CARPETA_EVIDENCIAS_LIQUIDACION = 'evidencias-liquidacion-venta-carbon';
    private const CARPETA_EVIDENCIAS_COMPROBANTE = 'evidencias-comprobante-venta-carbon';
    private const CARPETA_EVIDENCIAS_PAGO = 'evidencias-pago-venta-carbon';

    /**
     * Registra una liquidacion: agrupa las cargas de un tipo de carbon y
     * calcula el precio de cada una.
     *
     * @param array<string, mixed> $payload
     * @param array<int, UploadedFile> $archivos
     */
    public static function registrar_liquidacion(
        int $id_venta_carbon,
        array $payload,
        int $id_empleado,
        array $archivos = []
    ): array {
        $venta = VentaCarbonData::get_venta_por_id($id_venta_carbon);
        if ($venta === null) {
            return ApiResponse::error('Venta de carbon no encontrada');
        }

        $error = self::validar_cabecera_liquidacion($payload);
        if ($error !== null) {
            return ApiResponse::error($error);
        }

        $errorCargas = self::validar_cargas_liquidacion($venta, $payload, null);
        if ($errorCargas !== null) {
            return ApiResponse::error($errorCargas);
        }

        $evidencias = !empty($archivos)
            ? ArchivoHelper::guardarArchivos(self::CARPETA_EVIDENCIAS_LIQUIDACION, $archivos)
            : null;

        try {
            $idLiquidacion = VentaCarbonPagosData::registrar_liquidacion([
                'id_venta_carbon' => $id_venta_carbon,
                'id_empleado_registro' => $id_empleado,
                'id_tipo_carbon' => (int) $payload['id_tipo_carbon'],
                'codigo_orden_compra' => $payload['codigo_orden_compra'] ?? null,
                'numero_reporte' => $payload['numero_reporte'] ?? null,
                'fecha_aprobacion' => $payload['fecha_aprobacion'] ?? null,
                'version' => $payload['version'] ?? null,
                'evidencias' => $evidencias,
                'cargas' => $payload['cargas'] ?? [],
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage());
        }

        return ApiResponse::success(
            VentaCarbonPagosData::get_liquidaciones_por_venta([$id_venta_carbon]),
            'Liquidacion registrada correctamente'
        );
    }

    /**
     * Actualiza la liquidacion y recalcula los importes.
     *
     * @param array<string, mixed> $payload
     * @param array<int, UploadedFile> $archivos
     */
    public static function actualizar_liquidacion(
        int $id_venta_carbon,
        int $id_liquidacion,
        array $payload,
        int $id_empleado,
        array $archivos = []
    ): array {
        $venta = VentaCarbonData::get_venta_por_id($id_venta_carbon);
        if ($venta === null) {
            return ApiResponse::error('Venta de carbon no encontrada');
        }

        $liquidacion = self::buscar_liquidacion($venta, $id_liquidacion);
        if ($liquidacion === null) {
            return ApiResponse::error('La liquidacion no pertenece a la venta indicada');
        }

        $error = self::validar_cabecera_liquidacion($payload);
        if ($error !== null) {
            return ApiResponse::error($error);
        }

        $errorCargas = self::validar_cargas_liquidacion($venta, $payload, $id_liquidacion);
        if ($errorCargas !== null) {
            return ApiResponse::error($errorCargas);
        }

        // Si ya hay comprobante con pagos, los montos son irrompibles.
        $comprobante = $liquidacion->comprobante ?? null;
        if ($comprobante !== null && !empty($comprobante->pagos)) {
            return ApiResponse::error(
                'La liquidacion ya tiene pagos registrados; no se puede editar'
            );
        }

        $evidencias = !empty($archivos)
            ? ArchivoHelper::guardarArchivos(self::CARPETA_EVIDENCIAS_LIQUIDACION, $archivos)
            : null;

        try {
            VentaCarbonPagosData::actualizar_liquidacion([
                'id_liquidacion_venta_carbon' => $id_liquidacion,
                'id_tipo_carbon' => (int) $payload['id_tipo_carbon'],
                'codigo_orden_compra' => $payload['codigo_orden_compra'] ?? null,
                'numero_reporte' => $payload['numero_reporte'] ?? null,
                'fecha_aprobacion' => $payload['fecha_aprobacion'] ?? null,
                'version' => $payload['version'] ?? null,
                'evidencias' => $evidencias,
                'cargas' => $payload['cargas'] ?? [],
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage());
        }

        return ApiResponse::success(
            VentaCarbonPagosData::get_liquidaciones_por_venta([$id_venta_carbon]),
            'Liquidacion actualizada correctamente'
        );
    }

    /**
     * Confirma la liquidacion. Solo asi se puede registrar su comprobante.
     */
    public static function confirmar_liquidacion(
        int $id_venta_carbon,
        int $id_liquidacion,
        int $id_empleado
    ): array {
        $venta = VentaCarbonData::get_venta_por_id($id_venta_carbon);
        if ($venta === null) {
            return ApiResponse::error('Venta de carbon no encontrada');
        }

        $liquidacion = self::buscar_liquidacion($venta, $id_liquidacion);
        if ($liquidacion === null) {
            return ApiResponse::error('La liquidacion no pertenece a la venta indicada');
        }

        if ($liquidacion->esta_confirmado) {
            return ApiResponse::error('La liquidacion ya fue confirmada');
        }

        try {
            VentaCarbonPagosData::confirmar_liquidacion($id_liquidacion, $id_empleado);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage());
        }

        return ApiResponse::success(
            VentaCarbonPagosData::get_liquidaciones_por_venta([$id_venta_carbon]),
            'Liquidacion confirmada correctamente'
        );
    }

    /**
     * Registra el comprobante de una liquidacion confirmada.
     *
     * @param array<string, mixed> $payload
     * @param array<int, UploadedFile> $archivos
     */
    public static function registrar_comprobante(
        int $id_venta_carbon,
        int $id_liquidacion,
        array $payload,
        int $id_empleado,
        array $archivos = []
    ): array {
        $venta = VentaCarbonData::get_venta_por_id($id_venta_carbon);
        if ($venta === null) {
            return ApiResponse::error('Venta de carbon no encontrada');
        }

        $liquidacion = self::buscar_liquidacion($venta, $id_liquidacion);
        if ($liquidacion === null) {
            return ApiResponse::error('La liquidacion no pertenece a la venta indicada');
        }

        if (!$liquidacion->esta_confirmado) {
            return ApiResponse::error(
                'La liquidacion debe estar confirmada para registrar su comprobante'
            );
        }

        if (($liquidacion->comprobante ?? null) !== null) {
            return ApiResponse::error('La liquidacion ya tiene un comprobante registrado');
        }

        $evidencias = !empty($archivos)
            ? ArchivoHelper::guardarArchivos(self::CARPETA_EVIDENCIAS_COMPROBANTE, $archivos)
            : null;

        try {
            $idComprobante = VentaCarbonPagosData::registrar_comprobante([
                'id_liquidacion_venta_carbon' => $id_liquidacion,
                'id_empleado_registro' => $id_empleado,
                'codigo_comprobante' => (string) $payload['codigo_comprobante'],
                'fecha_emision' => (string) $payload['fecha_emision'],
                'observacion' => $payload['observacion'] ?? null,
                'evidencias' => $evidencias,
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage());
        }

        return ApiResponse::success(
            VentaCarbonPagosData::get_liquidaciones_por_venta([$id_venta_carbon]),
            'Comprobante registrado correctamente'
        );
    }

    /**
     * Registra un pago del cliente sobre el comprobante de una liquidacion.
     *
     * @param array<string, mixed> $payload
     * @param array<int, UploadedFile> $archivos
     */
    public static function registrar_pago(
        int $id_venta_carbon,
        int $id_comprobante,
        array $payload,
        int $id_empleado,
        array $archivos = []
    ): array {
        $venta = VentaCarbonData::get_venta_por_id($id_venta_carbon);
        if ($venta === null) {
            return ApiResponse::error('Venta de carbon no encontrada');
        }

        $comprobante = self::buscar_comprobante($venta, $id_comprobante);
        if ($comprobante === null) {
            return ApiResponse::error('El comprobante no pertenece a la venta indicada');
        }

        $medioPago = MedioPagoVentaCarbon::tryFrom((string) ($payload['medio_pago'] ?? ''));
        if ($medioPago === null) {
            return ApiResponse::error('El medio de pago no es valido');
        }

        $numeroOperacion = trim((string) ($payload['numero_operacion'] ?? ''));
        if ($medioPago->exige_numero_operacion() && $numeroOperacion === '') {
            return ApiResponse::error(
                'El numero de operacion es obligatorio para transferencias y depositos'
            );
        }

        $evidencias = !empty($archivos)
            ? ArchivoHelper::guardarArchivos(self::CARPETA_EVIDENCIAS_PAGO, $archivos)
            : null;

        try {
            VentaCarbonPagosData::registrar_pago([
                'id_comprobante_venta_carbon' => $id_comprobante,
                'id_empleado_registro' => $id_empleado,
                'id_cuenta_bancaria_empresa' => $payload['id_cuenta_bancaria_empresa'] ?? null,
                'medio_pago' => $medioPago->value,
                'numero_operacion' => $numeroOperacion,
                'fecha_hora_pago' => (string) $payload['fecha_hora_pago'],
                'es_para_detraccion' => $payload['es_para_detraccion'] ?? false,
                'monto_pagado' => (float) $payload['monto_pagado'],
                'observacion' => $payload['observacion'] ?? null,
                'evidencias' => $evidencias,
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage());
        }

        return ApiResponse::success(
            VentaCarbonPagosData::get_liquidaciones_por_venta([$id_venta_carbon]),
            'Pago registrado correctamente'
        );
    }

    /**
     * Busca la liquidacion dentro del arbol de la venta.
     */
    private static function buscar_liquidacion(object $venta, int $id_liquidacion): ?object
    {
        foreach ($venta->liquidaciones ?? [] as $liquidacion) {
            if ((int) $liquidacion->id_liquidacion_venta_carbon === $id_liquidacion) {
                return $liquidacion;
            }
        }

        return null;
    }

    /**
     * Busca el comprobante dentro del arbol de la venta.
     */
    private static function buscar_comprobante(object $venta, int $id_comprobante): ?object
    {
        foreach ($venta->liquidaciones ?? [] as $liquidacion) {
            $comprobante = $liquidacion->comprobante ?? null;
            if ($comprobante !== null
                && (int) $comprobante->id_comprobante_venta_carbon === $id_comprobante
            ) {
                return $comprobante;
            }
        }

        return null;
    }

    /**
     * Valida la cabecera de la liquidacion.
     * @param array<string, mixed> $payload
     */
    private static function validar_cabecera_liquidacion(array $payload): ?string
    {
        $idTipo = (int) ($payload['id_tipo_carbon'] ?? 0);
        if ($idTipo <= 0) {
            return 'Debe indicar el tipo de carbon de la liquidacion';
        }

        if (VentaCarbonData::get_tipo_carbon($idTipo) === null) {
            return 'El tipo de carbon de la liquidacion no existe';
        }

        if (empty($payload['cargas']) || !is_array($payload['cargas'])) {
            return 'Debe incluir al menos una carga en la liquidacion';
        }

        return null;
    }

    /**
     * Valida las cargas que se asignan a la liquidacion.
     *
     * Todas deben pertenecer a la venta, tener su llegada confirmada, estar
     * libres (o pertenecer ya a esta liquidacion) y ser del mismo tipo de
     * carbon que la liquidacion.
     *
     * @param array<string, mixed> $payload
     */
    private static function validar_cargas_liquidacion(
        object $venta,
        array $payload,
        ?int $id_liquidacion
    ): ?string {
        $idTipo = (int) ($payload['id_tipo_carbon'] ?? 0);

        $cargasPorId = [];
        foreach ($venta->cargas ?? [] as $carga) {
            $cargasPorId[(int) $carga->id_carga_venta_carbon] = $carga;
        }

        $vistas = [];

        foreach ($payload['cargas'] as $i => $item) {
            $n = $i + 1;
            $idCarga = (int) ($item['id_carga_venta_carbon'] ?? 0);

            if ($idCarga <= 0) {
                return "Carga #{$n}: la carga no es valida";
            }

            if (isset($vistas[$idCarga])) {
                return "Carga #{$n}: la carga esta repetida en la liquidacion";
            }
            $vistas[$idCarga] = true;

            $carga = $cargasPorId[$idCarga] ?? null;
            if ($carga === null) {
                return "Carga #{$n}: la carga no pertenece a la venta";
            }

            $liquidacionActual = (int) ($carga->id_liquidacion_venta_carbon ?? 0);
            if ($liquidacionActual > 0 && $liquidacionActual !== $id_liquidacion) {
                return "Carga #{$n}: la carga ya pertenece a otra liquidacion";
            }

            if ($carga->estado !== EstadoCargaVentaCarbon::LlegadaConfirmada->value
                && $carga->estado !== EstadoCargaVentaCarbon::EnLiquidacion->value
            ) {
                return "Carga #{$n}: solo se pueden liquidar cargas con la llegada confirmada";
            }

            if ((int) $carga->id_tipo_carbon !== $idTipo) {
                return "Carga #{$n}: el tipo de carbon no coincide con el de la liquidacion";
            }

            $precioBase = (float) ($item['precio_base'] ?? 0);
            if ($precioBase <= 0) {
                return "Carga #{$n}: debe indicar el precio base";
            }

            $descuento = (float) ($item['descuento'] ?? 0);
            $bono = (float) ($item['bono'] ?? 0);
            if ($descuento < 0 || $bono < 0) {
                return "Carga #{$n}: el descuento y el bono no pueden ser negativos";
            }

            if ($precioBase - $descuento + $bono < 0) {
                return "Carga #{$n}: el precio de materia prima no puede ser negativo";
            }
        }

        return null;
    }

    /**
     * Estado de la liquidacion segun el flujo (usado por el front).
     */
    public static function estado_liquidacion(bool $estaConfirmada): string
    {
        return EstadoLiquidacionVentaCarbon::desdeConfirmado($estaConfirmada)->value;
    }
}
