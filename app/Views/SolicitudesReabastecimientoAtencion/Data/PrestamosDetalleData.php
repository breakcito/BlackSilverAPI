<?php

namespace App\Views\SolicitudesReabastecimientoAtencion\Data;

use App\Models\PrestamoAlmacenDetalle;
use App\Models\PrestamoAlmacenDetalleLog;
use App\Shared\Enums\PrestamoAlmacen\EstadoDetallePrestamo;

class PrestamosDetalleData
{
    public static function get_detalles_por_prestamo(int $id_prestamo)
    {
        return PrestamoAlmacenDetalle::get_detalles(id_prestamo: $id_prestamo);
    }

    public static function crear_prestamo_detalle(
        int $id_prestamo,
        int $id_solicitud_detalle_original,
        int $id_producto,
        int $id_unidad_medida,
        float $contenido_por_presentacion,
        float $cantidad_solicitada,
        float $cantidad_solicitada_base,
        ?string $comentario,
        string $estado
    ): int {
        return PrestamoAlmacenDetalle::insertGetId([
            'id_prestamo_almacen'                    => $id_prestamo,
            'id_solicitud_reabastecimiento_detalle'  => $id_solicitud_detalle_original,
            'id_producto'                            => $id_producto,
            'id_unidad_medida'                       => $id_unidad_medida,
            'contenido_por_presentacion'             => $contenido_por_presentacion,
            'cantidad_solicitada'                    => $cantidad_solicitada,
            'cantidad_solicitada_base'               => $cantidad_solicitada_base,
            'cantidad_prestada'                      => 0,
            'cantidad_prestada_base'                 => 0,
            'cantidad_repuesta'                      => 0,
            'cantidad_repuesta_base'                 => 0,
            'comentario'                             => $comentario,
            'estado'                                 => $estado,
        ]);
    }

    public static function crear_log(
        int $id_prestamo_detalle,
        int $id_empleado,
        string $descripcion,
        EstadoDetallePrestamo $estado,
        ?string $created_at = null,
    ) {
        return PrestamoAlmacenDetalleLog::crear_log(
            id_prestamo_almacen_detalle: $id_prestamo_detalle,
            id_empleado: $id_empleado,
            descripcion: $descripcion,
            estado: $estado,
            created_at: $created_at
        );
    }
}
