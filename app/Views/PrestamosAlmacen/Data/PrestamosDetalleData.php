<?php

namespace App\Views\PrestamosAlmacen\Data;

use Illuminate\Support\Facades\DB;

class PrestamosDetalleData
{
    /**
     * Obtiene el detalle de los ítems de un préstamo específico.
     */
    public static function get_detalles_prestamo(int $id_prestamo): array
    {
        $sql = '
        SELECT
            pad.id AS id_prestamo_detalle,
            pad.id_solicitud_reabastecimiento_detalle,
            srd.id_producto,
            prod.nombre AS producto,
            srd.contenido_por_presentacion, -- cuantas unidades de medida base hay en una unidad de medida de la solicitud
            pad.cantidad_solicitada, -- lo que le piden prestado
            pad.cantidad_solicitada_base,
            pad.cantidad_prestada, -- lo que va prestando
            pad.cantidad_prestada_base,
            pad.cantidad_repuesta, -- lo que va siendo repuesto por logistica
            pad.cantidad_repuesta_base,
            pad.comentario,            
            um.id as id_unidad_medida_sol, -- unidad de medida del detalle de la solicitud
            um.abreviatura AS unidad_medida_sol_abv,
            um_base.id as id_unidad_medida_base, -- unidad de medida base del producto
            um_base.abreviatura AS unidad_medida_base_abv,
            pad.estado
        FROM
            prestamo_almacen_detalle pad
        INNER JOIN solicitud_reabastecimiento_detalle srd ON srd.id = pad.id_solicitud_reabastecimiento_detalle
        INNER JOIN producto prod ON prod.id = srd.id_producto
        INNER JOIN unidad_medida um ON um.id = srd.id_unidad_medida
        INNER JOIN unidad_medida um_base ON um_base.id = prod.id_unidad_medida_base
        WHERE pad.id_prestamo_almacen = :id_prestamo
        ORDER BY prod.nombre ASC
        ';
        return DB::select($sql, [
            'id_prestamo' => $id_prestamo
        ]);
    }

    /**
     * Obtener el historial de trazabilidad de un ítem de préstamo.
     */
    public static function get_detalle_logs(int $id_prestamo_detalle): array
    {
        return DB::select('
            SELECT
                log.id as id_log,
                log.estado,
                log.descripcion,
                log.created_at,
                CONCAT(e.nombre, " ", e.apellido) AS empleado,
                e.path_foto
            FROM
                prestamo_almacen_detalle_log log
            INNER JOIN empleado e ON e.id = log.id_empleado
            WHERE
                log.id_prestamo_almacen_detalle = :id
            ORDER BY
                log.created_at DESC
        ', ["id" => $id_prestamo_detalle]);
    }
}
