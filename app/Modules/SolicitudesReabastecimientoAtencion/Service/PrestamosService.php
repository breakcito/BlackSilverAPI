<?php

namespace App\Modules\SolicitudesReabastecimientoAtencion\Service;

use App\Data\ProductosData;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalleLog;
use App\Shared\Responses\ApiResponse;
use App\Modules\SolicitudesReabastecimientoAtencion\Data\AuxData;
use App\Modules\SolicitudesReabastecimientoAtencion\Data\PrestamosData;
use App\Modules\SolicitudesReabastecimientoAtencion\Data\SolicitudesDetalleData;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalle;
use Illuminate\Support\Facades\DB;

class PrestamosService
{
    public static function get_prestamos_por_solicitud(int $id_solicitud_reabastecimiento)
    {
        $data = PrestamosData::get_prestamos_por_solicitud($id_solicitud_reabastecimiento);
        return ApiResponse::success($data);
    }

    public static function crear_prestamo(
        int $id_solicitud_reabastecimiento,
        int $id_almacen_prestamista,
        int $id_empleado_registro,
        bool $es_auditable,
        array $detalles,
        ?string $fecha_limite_devolucion,
        ?string $observacion,
    ) {
        // 1. VALIDACIÓN DE STOCK EN TIEMPO REAL PARA TODOS LOS ÍTEMS (Pre-validación antes de cualquier inserción o transacción)
        foreach ($detalles as $detalle) {
            $srd = SolicitudesDetalleData::get_detalle_para_prestamo($detalle['id_solicitud_reabastecimiento_detalle']);
            if (!$srd) {
                continue;
            }

            $cantidad_solicitada = (float) $detalle['cantidad_solicitada'];
            $cantidad_solicitada_base = $cantidad_solicitada * (float) $srd->contenido_por_presentacion;

            $stocks = ProductosData::get_stock_total_almacen_por_productos($id_almacen_prestamista, [(int) $srd->id_producto]);
            $stock_total_base = 0.0;
            if (!empty($stocks)) {
                $stock_total_base = (float) $stocks[0]->stock_total_base;
            }

            if ($stock_total_base < $cantidad_solicitada_base) {
                $nombre_producto = AuxData::get_nombre_producto($srd->id_producto);
                $stock_formateado = round($stock_total_base / $srd->contenido_por_presentacion, 2);
                return ApiResponse::error("¡Ups! El stock de '{$nombre_producto}' ha cambiado. Disponible: {$stock_formateado}, Solicitado: {$cantidad_solicitada}. La operación fue abortada.");
            }
        }

        // 2. SI TODO ESTÁ CORRECTO, INICIAR TRANSACCIÓN Y REGISTROS
        return DB::transaction(function () use ($id_solicitud_reabastecimiento, $id_almacen_prestamista, $id_empleado_registro, $es_auditable, $detalles, $fecha_limite_devolucion, $observacion) {

            $correlativoData = PrestamosData::get_nuevo_correlativo();

            // Obtener el almacén solicitante directamente desde la capa de datos de la vista
            $id_almacen_solicitante = AuxData::get_almacen_solicitante_by_id_solicitud($id_solicitud_reabastecimiento);

            $id_prestamo = PrestamosData::crear_prestamo(
                $id_solicitud_reabastecimiento,
                $id_almacen_solicitante,
                $id_almacen_prestamista,
                $id_empleado_registro,
                $correlativoData['correlativo'],
                $correlativoData['numero_correlativo'],
                $es_auditable,
                now()->toDateTimeString(),
                $fecha_limite_devolucion,
                $observacion
            );

            foreach ($detalles as $detalle) {
                // Obtener el detalle de la solicitud usando la capa de datos de la vista
                $srd = SolicitudesDetalleData::get_detalle_para_prestamo($detalle['id_solicitud_reabastecimiento_detalle']);
                if (!$srd)
                    continue;

                SolicitudesDetalleData::insert_detalle_log(
                    $srd->id,
                    $id_empleado_registro,
                    EstadoSolicitudDetalle::SolicitandoPrestamo->getGlosa(),
                    EstadoSolicitudDetalleLog::SolicitandoPrestamo
                );

                $cantidad_solicitada = (float) $detalle['cantidad_solicitada'];
                $cantidad_solicitada_base = $cantidad_solicitada * (float) $srd->contenido_por_presentacion;

                $id_detalle = PrestamosData::crear_detalle(
                    (int) $id_prestamo,
                    (int) $srd->id,
                    (int) $srd->id_producto,
                    (int) $srd->id_unidad_medida,
                    (float) $srd->contenido_por_presentacion,
                    $cantidad_solicitada,
                    $cantidad_solicitada_base,
                    $detalle['comentario'] ?? null,
                );

                // Registrar trazabilidad inicial del préstamo
                PrestamosData::crear_detalle_log(
                    id_prestamo_detalle: $id_detalle,
                    id_empleado: $id_empleado_registro,
                );
            }

            return ApiResponse::success($id_prestamo, 'Préstamo registrado correctamente');
        });
    }

    public static function get_prestamo_por_id(int $id_prestamo)
    {
        $cabecera = (array) PrestamosData::get_prestamo_por_id($id_prestamo);
        if (!$cabecera)
            return ApiResponse::error('Préstamo no encontrado');

        $cabecera['detalles'] = PrestamosData::get_detalles_por_prestamo($id_prestamo);
        return ApiResponse::success($cabecera);
    }
}
