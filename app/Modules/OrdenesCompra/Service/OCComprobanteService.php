<?php

namespace App\Modules\OrdenesCompra\Service;

use App\Modules\OrdenesCompra\Data\OCComprobanteData;
use App\Shared\Enums\_Generic\Moneda;
use App\Shared\Enums\_Generic\TipoComprobante;
use App\Shared\Enums\OrdenCompra\EstadoOCComprobante;
use App\Shared\Helpers\ArchivoHelper;
use Illuminate\Support\Facades\DB;
use App\Shared\Responses\ApiResponse;

class OCComprobanteService
{
    /**
     * Registrar un nuevo comprobante asociado a una o varias recepciones de una Orden de Compra.
     * 
     * @param int $id_empleado_registro ID del empleado que realiza el registro.
     * @param int $id_orden_compra ID de la Orden de Compra asociada.
     * @param TipoComprobante $tipo_comprobante Tipo de documento (Factura, Boleta, etc).
     * @param array $evidencias Lista de archivos físicos (UploadedFile) de evidencias del comprobante.
     * @param array $ids_recepciones Lista de IDs de las recepciones (orden_compra_recepcion) que ampara este comprobante.
     */
    public static function registrar_comprobante(
        int $id_empleado_registro,
        int $id_orden_compra,
        TipoComprobante $tipo_comprobante,
        string $serie,
        string $numero,
        string $fecha_emision,
        ?string $observacion,
        array $evidencias,
        //
        Moneda $moneda,
        float $tipo_cambio_venta_aplicado,
        bool $es_auditable,
        //
        float $total_antes_igv,
        float $total_antes_igv_soles,
        bool $incluye_igv,
        float $porcentaje_igv,
        float $monto_igv,
        float $monto_igv_soles,
        float $total_despues_igv,
        float $total_despues_igv_soles,
        //
        array $ids_recepciones = []
    ) {
        return DB::transaction(function () use ($id_empleado_registro, $id_orden_compra, $tipo_comprobante, $serie, $numero, $fecha_emision, $observacion, $evidencias, $moneda, $tipo_cambio_venta_aplicado, $es_auditable, $total_antes_igv, $total_antes_igv_soles, $incluye_igv, $porcentaje_igv, $monto_igv, $monto_igv_soles, $total_despues_igv, $total_despues_igv_soles, $ids_recepciones) {
            $evidenciasJson = null;
            if (!empty($evidencias)) {
                $evidenciasData = ArchivoHelper::guardarArchivos('ordenes-compra-comprobantes', $evidencias);
                $evidenciasJson = json_encode($evidenciasData);
            }

            $id_comprobante = OCComprobanteData::crear_comprobante(
                id_empleado_registro: $id_empleado_registro,
                id_orden_compra: $id_orden_compra,
                tipo_comprobante: $tipo_comprobante,
                serie: $serie,
                numero: $numero,
                fecha_emision: $fecha_emision,
                observacion: $observacion,
                evidencias: $evidenciasJson,
                moneda: $moneda,
                tipo_cambio_venta_aplicado: $tipo_cambio_venta_aplicado,
                es_auditable: $es_auditable ? 1 : 0,
                total_antes_igv: $total_antes_igv,
                total_antes_igv_soles: $total_antes_igv_soles,
                incluye_igv: $incluye_igv ? 1 : 0,
                porcentaje_igv: $porcentaje_igv,
                monto_igv: $monto_igv,
                monto_igv_soles: $monto_igv_soles,
                total_despues_igv: $total_despues_igv,
                total_despues_igv_soles: $total_despues_igv_soles,
                estado: EstadoOCComprobante::Generado->value
            );

            foreach ($ids_recepciones as $id_recepcion) {
                OCComprobanteData::vincular_recepcion($id_comprobante, $id_recepcion);
            }

            return ApiResponse::success($id_comprobante, "Comprobante registrado exitosamente");
        });
    }

    public static function listar_comprobantes(int $id_orden_compra)
    {
        $comprobantes = OCComprobanteData::get_comprobantes(id_orden_compra: $id_orden_compra);
        foreach ($comprobantes as $comp) {
            $comp->evidencias = $comp->evidencias ? json_decode($comp->evidencias) : null;
            $comp->recepciones_agrupadas = OCComprobanteData::get_recepciones_agrupadas($comp->id_comprobante);
        }

        return ApiResponse::success($comprobantes);
    }
}