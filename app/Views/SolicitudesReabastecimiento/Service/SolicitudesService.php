<?php

namespace App\Views\SolicitudesReabastecimiento\Service;

use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalle;
use App\Shared\Responses\ApiResponse;
use App\Views\SolicitudesReabastecimiento\Data\SolicitudesData;
use App\Views\SolicitudesReabastecimiento\Data\SolicitudesDetalleData;

class SolicitudesService
{
    
    // Obtener todas la lista de solicitudes hechas por el empleado
    public static function get_solicitudes(int $id_empleado, int $mes, int $yearcito)
    {
        $data = SolicitudesData::get_solicitudes(
            id_empleado: $id_empleado,
            mes: $mes,
            yearcito: $yearcito
        );

        return ApiResponse::success($data);
    }

    // Registrar una solicitud y sus detalles
    public static function crear_solicitud(
        int $id_almacen_solicitante,
        int $id_empleado_solicitante,
        string $premura,
        ?string $observacion,
        ?string $fecha_entrega_requerida,
        // id_producto, id_unidad_medida, cantidad_solicitada, 
        // contenido_por_presentacion, cantidad_solicitada_base, 
        // comentario
        array $detalles 
    ) {
        // 1. Generar Correlativo
        $correlativoData = SolicitudesData::get_nuevo_correlativo($id_almacen_solicitante);
        $correlativo = $correlativoData['correlativo'];
        $numero_correlativo = $correlativoData['numero_correlativo'];

        // 2. Crear cabecera
        $id_solicitud = SolicitudesData::crear_solicitud(
            $id_almacen_solicitante,
            $id_empleado_solicitante,
            $correlativo,
            $numero_correlativo,
            $observacion,
            $premura,
            $fecha_entrega_requerida
        );

        // 3. Crear detalles
        foreach ($detalles as $detalle) {
            $id_producto = $detalle['id_producto'];
            $id_unidad_medida = $detalle['id_unidad_medida'];
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

            $estadoEnum = EstadoSolicitudDetalle::EsperandoAprobacion;
            SolicitudesDetalleData::insert_detalle_log(
                (int)$id_solicitud_detalle,
                $id_empleado_solicitante,
                $estadoEnum->getGlosa($comentario),
                $estadoEnum->value
            );
        }

        return ApiResponse::success(
            SolicitudesData::get_solicitud_by_id($id_solicitud),
            'Solicitud generada correctamente'
        );
    }

    // Obtener los detalles de una solicitud
    public static function get_detalles_solicitud(int $id_solicitud)
    {
        $detalles = SolicitudesDetalleData::get_detalles_solicitud($id_solicitud);
        return ApiResponse::success($detalles);
    }


    // Obtener la trazabildiad de un detalle
    public static function get_trazabilidad_by_detalle(int $id_solicitud_detalle)
    {
        $detalles = SolicitudesDetalleData::get_trazabilidad_by_detalle($id_solicitud_detalle);
        return ApiResponse::success($detalles);
    }
}
