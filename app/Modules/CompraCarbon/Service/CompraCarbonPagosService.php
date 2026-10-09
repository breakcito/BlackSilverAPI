<?php

namespace App\Modules\CompraCarbon\Service;

use App\Modules\CompraCarbon\Data\CompraCarbonData;
use App\Modules\CompraCarbon\Data\CompraCarbonPagosData;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\UploadedFile;

class CompraCarbonPagosService
{
    private const CARPETA_COMPROBANTES_PROV = 'comprobantes-compra-carbon';
    private const CARPETA_PAGOS_PROV = 'pagos-compra-carbon';
    private const CARPETA_COMPROBANTES_TRANS = 'comprobantes-transporte-carbon';
    private const CARPETA_PAGOS_TRANS = 'pagos-transporte-carbon';

    /**
     * Registra el comprobante de compra entregado por el proveedor.
     * @param array<string, mixed> $payload
     * @param array<int, UploadedFile> $archivos
     */
    public static function registrar_comprobante_proveedor(
        int $id_compra_carbon,
        array $payload,
        int $id_empleado,
        array $archivos = []
    ): array {
        $compra = CompraCarbonData::get_compra_con_detalles($id_compra_carbon);
        if ($compra['cabecera'] === null) {
            return ApiResponse::error('Compra no encontrada');
        }

        if (empty($compra['cabecera']->aplica_igv)) {
            return ApiResponse::error('Esta compra no aplica IGV, los pagos se efectúan directamente sin comprobante');
        }

        $idsCargas = $payload['ids_cargas'] ?? [];
        if (empty($idsCargas) || !is_array($idsCargas)) {
            return ApiResponse::error('Debe seleccionar al menos una carga para este comprobante');
        }

        $evidencias = [];
        if (!empty($archivos)) {
            $evidencias = ArchivoHelper::guardarArchivos(self::CARPETA_COMPROBANTES_PROV, $archivos);
        }

        try {
            $idComprobante = CompraCarbonPagosData::registrar_comprobante_proveedor([
                'id_compra_carbon' => $id_compra_carbon,
                'id_empleado_registro' => $id_empleado,
                'codigo_comprobante' => (string) $payload['codigo_comprobante'],
                'fecha_emision' => (string) $payload['fecha_emision'],
                'observacion' => $payload['observacion'] ?? null,
                'evidencias' => $evidencias,
                'con_detraccion' => !empty($payload['con_detraccion']),
                'porcentaje_detraccion' => isset($payload['porcentaje_detraccion']) ? (float) $payload['porcentaje_detraccion'] : 10.0,
                'ids_cargas' => array_map('intval', $idsCargas),
                'anticipos' => $payload['anticipos'] ?? [],
            ]);

            return ApiResponse::success([
                'id_comprobante_compra_carbon' => $idComprobante,
            ], 'Comprobante del proveedor registrado satisfactoriamente');
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    /**
     * Registra un pago al proveedor (contra comprobante o directo si no aplica IGV).
     * @param array<string, mixed> $payload
     * @param array<int, UploadedFile> $archivos
     */
    public static function registrar_pago_proveedor(
        int $id_compra_carbon,
        array $payload,
        int $id_empleado,
        array $archivos = []
    ): array {
        $compra = CompraCarbonData::get_compra_con_detalles($id_compra_carbon);
        if ($compra['cabecera'] === null) {
            return ApiResponse::error('Compra no encontrada');
        }

        $aplicaIgv = !empty($compra['cabecera']->aplica_igv);
        $idComprobante = isset($payload['id_comprobante_compra_carbon']) && (int) $payload['id_comprobante_compra_carbon'] > 0
            ? (int) $payload['id_comprobante_compra_carbon']
            : null;

        if ($aplicaIgv && $idComprobante === null) {
            return ApiResponse::error('Para compras con IGV el pago debe estar sujeto a un comprobante');
        }

        $evidencias = [];
        if (!empty($archivos)) {
            $evidencias = ArchivoHelper::guardarArchivos(self::CARPETA_PAGOS_PROV, $archivos);
        }

        try {
            $idPago = CompraCarbonPagosData::registrar_pago_proveedor([
                'id_compra_carbon' => $id_compra_carbon,
                'id_comprobante_compra_carbon' => $idComprobante,
                'id_cuenta_bancaria_empresa' => (int) $payload['id_cuenta_bancaria_empresa'],
                'id_cuenta_bancaria_proveedor' => isset($payload['id_cuenta_bancaria_proveedor']) && (int) $payload['id_cuenta_bancaria_proveedor'] > 0
                    ? (int) $payload['id_cuenta_bancaria_proveedor']
                    : null,
                'id_empleado_registro' => $id_empleado,
                'medio_pago' => (string) $payload['medio_pago'],
                'numero_operacion' => $payload['numero_operacion'] ?? null,
                'fecha_hora_pago' => (string) $payload['fecha_hora_pago'],
                'es_para_detraccion' => !empty($payload['es_para_detraccion']),
                'observacion' => $payload['observacion'] ?? null,
                'evidencias' => $evidencias,
                'monto_pagado' => (float) $payload['monto_pagado'],
                'ids_cargas' => isset($payload['ids_cargas']) && is_array($payload['ids_cargas']) ? array_map('intval', $payload['ids_cargas']) : [],
                'anticipos' => $payload['anticipos'] ?? [],
            ]);

            return ApiResponse::success([
                'id_pago_compra_carbon' => $idPago,
            ], 'Pago al proveedor registrado con éxito');
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    /**
     * Registra el comprobante de transporte / flete.
     * @param array<string, mixed> $payload
     * @param array<int, UploadedFile> $archivos
     */
    public static function registrar_comprobante_transporte(
        int $id_compra_carbon,
        array $payload,
        int $id_empleado,
        array $archivos = []
    ): array {
        $idsCargas = $payload['ids_cargas'] ?? [];
        if (empty($idsCargas) || !is_array($idsCargas)) {
            return ApiResponse::error('Debe seleccionar las cargas asociadas a este flete');
        }

        $evidencias = [];
        if (!empty($archivos)) {
            $evidencias = ArchivoHelper::guardarArchivos(self::CARPETA_COMPROBANTES_TRANS, $archivos);
        }

        try {
            $idComprobante = CompraCarbonPagosData::registrar_comprobante_transporte([
                'id_compra_carbon' => $id_compra_carbon,
                'id_empleado_registro' => $id_empleado,
                'id_transportista' => (int) $payload['id_transportista'],
                'codigo_comprobante' => (string) $payload['codigo_comprobante'],
                'fecha_emision' => (string) $payload['fecha_emision'],
                'observacion' => $payload['observacion'] ?? null,
                'evidencias' => $evidencias,
                'con_detraccion' => !empty($payload['con_detraccion']),
                'porcentaje_detraccion' => isset($payload['porcentaje_detraccion']) ? (float) $payload['porcentaje_detraccion'] : 4.0,
                'ids_cargas' => array_map('intval', $idsCargas),
            ]);

            return ApiResponse::success([
                'id_comprobante_transporte_carbon' => $idComprobante,
            ], 'Comprobante de transporte registrado exitosamente');
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    /**
     * Registra un pago de transporte / flete.
     * @param array<string, mixed> $payload
     * @param array<int, UploadedFile> $archivos
     */
    public static function registrar_pago_transporte(
        int $id_compra_carbon,
        array $payload,
        int $id_empleado,
        array $archivos = []
    ): array {
        $evidencias = [];
        if (!empty($archivos)) {
            $evidencias = ArchivoHelper::guardarArchivos(self::CARPETA_PAGOS_TRANS, $archivos);
        }

        try {
            $idPago = CompraCarbonPagosData::registrar_pago_transporte([
                'id_compra_carbon' => $id_compra_carbon,
                'id_comprobante_transporte_carbon' => (int) $payload['id_comprobante_transporte_carbon'],
                'id_cuenta_bancaria_empresa' => (int) $payload['id_cuenta_bancaria_empresa'],
                'id_cuenta_bancaria_transportista' => isset($payload['id_cuenta_bancaria_transportista']) && (int) $payload['id_cuenta_bancaria_transportista'] > 0
                    ? (int) $payload['id_cuenta_bancaria_transportista']
                    : null,
                'id_empleado_registro' => $id_empleado,
                'medio_pago' => (string) $payload['medio_pago'],
                'numero_operacion' => $payload['numero_operacion'] ?? null,
                'fecha_hora_pago' => (string) $payload['fecha_hora_pago'],
                'es_para_detraccion' => !empty($payload['es_para_detraccion']),
                'observacion' => $payload['observacion'] ?? null,
                'evidencias' => $evidencias,
                'monto_pagado' => (float) $payload['monto_pagado'],
            ]);

            return ApiResponse::success([
                'id_pago_transporte_carbon' => $idPago,
            ], 'Pago de flete registrado satisfactoriamente');
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage());
        }
    }
}
