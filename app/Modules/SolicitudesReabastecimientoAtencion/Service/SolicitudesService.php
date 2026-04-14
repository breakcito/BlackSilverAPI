<?php

namespace App\Modules\SolicitudesReabastecimientoAtencion\Service;

use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalle;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalle;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalleLog;
use App\Shared\Responses\ApiResponse;
use App\Modules\SolicitudesReabastecimientoAtencion\Data\AuxData;
use App\Modules\SolicitudesReabastecimientoAtencion\Data\SolicitudesData;
use App\Modules\SolicitudesReabastecimientoAtencion\Data\SolicitudesDetalleData;
use Illuminate\Support\Facades\DB;

class SolicitudesService
{
    /**
     * Obtiene las solicitudes por almacén y periodo
     */
    public static function get_solicitudes(int $id_almacen, string $mes, string $yearcito)
    {
        $data = SolicitudesData::get_resumen_solicitudes($id_almacen, $mes, $yearcito);
        return ApiResponse::success($data);
    }

    /**
     * Obtiene los detalles de una solicitud
     */
    public static function get_detalles_solicitud(int $id_solicitud)
    {
        $data = SolicitudesDetalleData::get_detalles_by_solicitud($id_solicitud);
        return ApiResponse::success($data);
    }

    /**
     * Cambia el estado de uno o varios productos (Aprobado/Rechazado) y registra en Timeline.
     */
    public static function update_detalle_estado(int $id_empleado, array $ids_detalles, string $nuevo_estado, ?string $comentario_decision = null)
    {
        return DB::transaction(function () use ($id_empleado, $ids_detalles, $nuevo_estado, $comentario_decision) {

            foreach ($ids_detalles as $id_detalle) {
                SolicitudesDetalleData::update_detalle_estado(
                    (int) $id_detalle,
                    $nuevo_estado,
                    $id_empleado,
                    $comentario_decision
                );

                // Determinar el Enum para el log
                $estadoEnum = EstadoSolicitudDetalle::from($nuevo_estado);

                // Colocar en estado de proceso a la solicitudes si uno de sus detalles es aprobado o consultado con logistica
                if (EstadoSolicitudDetalle::Aprobado->value == $nuevo_estado) {
                    $solicitud = SolicitudesDetalleData::get_id_solicitud_by_detalle((int) $id_detalle);
                    SolicitudesData::update_solicitud_estado(
                        (int) $solicitud->id_solicitud_reabastecimiento,
                        $nuevo_estado
                    );
                }

                // Si el detalle de la solicitud viene por un detalle de requerimiento, actualizamos su estado
                $detalleData = SolicitudesDetalleData::get_detalle_by_id((int) $id_detalle);
                if ($detalleData->id_requerimiento_almacen_detalle != null) {
                    $id_detalle_req = (int) $detalleData->id_requerimiento_almacen_detalle;
                    $estadoDetalleReqEnum = EstadoSolicitudDetalle::Aprobado->value == $nuevo_estado ? EstadoRequerimientoDetalle::AprobadoLogistica : EstadoRequerimientoDetalle::RechazadoLogistica;

                    // actualizamos el estado
                    AuxData::update_detalle_requerimiento_estado(
                        $id_detalle_req,
                        $estadoDetalleReqEnum->value,
                        $id_empleado,
                        $comentario_decision
                    );

                    // insertamos su trazabilidad
                    AuxData::insert_detalle_requerimiento_log(
                        $id_detalle_req,
                        $id_empleado,
                        $estadoDetalleReqEnum->getGlosa(),
                        $estadoDetalleReqEnum->value
                    );
                }

                $descripcion = $estadoEnum->getGlosa();
                SolicitudesDetalleData::insert_detalle_log(
                    (int) $id_detalle,
                    $id_empleado,
                    $comentario_decision ?? $descripcion,
                    EstadoSolicitudDetalleLog::from($nuevo_estado)
                );
            }

            $mensaje = count($ids_detalles) > 1
                ? 'Estado de los productos actualizado correctamente'
                : 'Estado del producto actualizado correctamente';

            return ApiResponse::success(null, $mensaje);
        });
    }

    /**
     * Obtiene la trazabilidad de un detalle de solicitud
     */
    public static function get_trazabilidad(int $id_detalle)
    {
        $data = SolicitudesDetalleData::get_detalle_logs($id_detalle);
        return ApiResponse::success($data);
    }
}
