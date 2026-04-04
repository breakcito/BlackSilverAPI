<?php

namespace App\Views\SolicitudesReabastecimiento\Data;

use App\Models\SolicitudReabastecimientoDetalle;
use App\Models\SolicitudReabastecimientoDetalleLog;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalle;

class SolicitudesDetalleData
{

    // Obtener el detalle de una solicitud
    public static function get_detalles_solicitud(int $id_solicitud_reabastecimiento)
    {
        return SolicitudReabastecimientoDetalle::get_detalles_solicitud(
            id_solicitud_reabastecimiento: $id_solicitud_reabastecimiento
        );
    }

    // Funcion helpder que ayuda a crear un detalle de solicitud
    public static function crear_detalle_solicitud(
        int $id_solicitud,
        int $id_producto,
        int $id_unidad_medida,
        float $cantidad_solicitada,
        float $contenido_por_presentacion,
        float $cantidad_solicitada_base,
        ?string $comentario
    ) {
        return SolicitudReabastecimientoDetalle::crear_detalle(
            id_solicitud_reabastecimiento: $id_solicitud,
            id_producto: $id_producto,
            id_unidad_medida: $id_unidad_medida,
            cantidad_solicitada: $cantidad_solicitada,
            contenido_por_presentacion: $contenido_por_presentacion,
            cantidad_solicitada_base: $cantidad_solicitada_base,
            id_requerimiento_almacen_detalle: null,
            comentario: $comentario
        );
    }

    // Registrar en trazabilidad el cambio de estado de un detalle de solicitud de reabastecimiento
    public static function insert_detalle_log(
        int $id_solicitud_detalle,
        int $id_empleado,
        string $descripcion,
        EstadoSolicitudDetalle $estado
    ) {
        return SolicitudReabastecimientoDetalleLog::crear_log(
            id_solicitud_detalle: $id_solicitud_detalle,
            id_empleado: $id_empleado,
            descripcion: $descripcion,
            estado: $estado
        );
    }

    /**
     * Obtiene la trazabilidad de un detalle de solicitud
     */
    public static function get_trazabilidad_by_detalle(int $id_detalle)
    {
        return SolicitudReabastecimientoDetalleLog::get_logs(
            id_solicitud_detalle: $id_detalle
        );
    }
}
