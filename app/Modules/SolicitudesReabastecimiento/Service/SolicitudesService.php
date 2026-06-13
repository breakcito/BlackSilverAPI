<?php

namespace App\Modules\SolicitudesReabastecimiento\Service;

use App\Shared\Enums\_Generic\Premura;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalleLog;
use App\Shared\Responses\ApiResponse;
use App\Modules\SolicitudesReabastecimiento\Data\SolicitudesData;
use App\Modules\SolicitudesReabastecimiento\Data\SolicitudesDetalleData;

class SolicitudesService
{

    /**
     * Obtener todas la lista de solicitudes hechas por el empleado
     */
    public static function get_solicitudes(int $id_empleado, int $mes, int $yearcito)
    {
        $data = SolicitudesData::get_solicitudes(
            id_empleado: $id_empleado,
            mes: $mes,
            yearcito: $yearcito
        );

        return ApiResponse::success($data);
    }

    /**
     * Registrar una solicitud y sus detalles
     */
    public static function crear_solicitud(
        int $id_almacen_solicitante,
        int $id_empleado_solicitante,
        Premura $premura,
        bool $es_auditable,
        // id_producto, id_unidad_medida, cantidad_solicitada, 
        // contenido_por_presentacion 
        // comentario
        array $detalles,
        ?string $observacion,
        ?string $fecha_entrega_requerida,
    ) {
        // 1. Generar Correlativo
        $correlativoData = SolicitudesData::get_nuevo_correlativo();
        $correlativo = $correlativoData['correlativo'];
        $numero_correlativo = $correlativoData['numero_correlativo'];

        // 2. Crear cabecera
        $id_solicitud = SolicitudesData::crear_solicitud(
            $id_almacen_solicitante,
            $id_empleado_solicitante,
            $correlativo,
            $numero_correlativo,
            $premura,
            $es_auditable,
            $observacion,
            $fecha_entrega_requerida
        );

        // 3. Crear detalles
        foreach ($detalles as $detalle) {
            $id_producto = (int) $detalle['id_producto'];
            $id_unidad_medida = (int) $detalle['id_unidad_medida'];
            $cantidad_solicitada = (float) $detalle['cantidad_solicitada'];
            $contenido_por_presentacion = (float) $detalle['contenido_por_presentacion'];
            $cantidad_solicitada_base = $cantidad_solicitada * $contenido_por_presentacion;
            $comentario = $detalle['comentario'] ?? null;

            $id_solicitud_detalle = SolicitudesDetalleData::crear_detalle_solicitud(
                $id_solicitud,
                $id_producto,
                $id_unidad_medida,
                $cantidad_solicitada,
                $contenido_por_presentacion,
                $cantidad_solicitada_base,
                $comentario
            );

            $estadoEnum = EstadoSolicitudDetalleLog::EsperandoAprobacion;
            SolicitudesDetalleData::insert_detalle_log(
                (int) $id_solicitud_detalle,
                $id_empleado_solicitante,
                $estadoEnum->getGlosa($comentario),
                $estadoEnum
            );
        }

        return ApiResponse::success(
            SolicitudesData::get_solicitud_by_id($id_solicitud),
            'Solicitud generada correctamente'
        );
    }

    /**
     * Obtener los detalles de una solicitud
     */
    public static function get_detalles_solicitud(int $id_solicitud)
    {
        $detalles = SolicitudesDetalleData::get_detalles_solicitud($id_solicitud);
        return ApiResponse::success($detalles);
    }


    /**
     * Obtener la trazabildiad de un detalle
     */
    public static function get_trazabilidad_by_detalle(int $id_solicitud_detalle)
    {
        $detalles = SolicitudesDetalleData::get_trazabilidad_by_detalle($id_solicitud_detalle);
        return ApiResponse::success($detalles);
    }
}
