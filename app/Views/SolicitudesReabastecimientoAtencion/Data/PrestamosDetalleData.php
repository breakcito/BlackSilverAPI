<?php

namespace App\Views\SolicitudesReabastecimientoAtencion\Data;

use Illuminate\Support\Facades\DB;
use App\Models\PrestamoAlmacenDetalle;

class PrestamosDetalleData
{
    public static function get_detalles_por_prestamo(int $id_prestamo)
    {
        $sql = "
        SELECT
            pad.id,
            pad.id_prestamo_almacen,
            pad.id_solicitud_reabastecimiento_detalle,
            pad.cantidad_solicitada,
            pad.cantidad_solicitada_base,
            pad.cantidad_prestada,
            pad.cantidad_prestada_base,
            pad.cantidad_repuesta,
            pad.cantidad_repuesta_base,
            pad.comentario,
            pad.estado,
            p.nombre AS producto,
            um.abreviatura AS unidad_medida
        FROM
            prestamo_almacen_detalle pad
        INNER JOIN solicitud_reabastecimiento_detalle srd ON srd.id = pad.id_solicitud_reabastecimiento_detalle
        INNER JOIN producto p ON p.id = srd.id_producto
        INNER JOIN unidad_medida um ON um.id = srd.id_unidad_medida
        WHERE
            pad.id_prestamo_almacen = :id_prestamo
        ";

        return DB::select($sql, ['id_prestamo' => $id_prestamo]);
    }

    public static function crear_prestamo_detalle(
        int $id_prestamo,
        int $id_solicitud_detalle_original,
        float $cantidad_solicitada,
        float $cantidad_solicitada_base,
        ?string $comentario,
        string $estado
    ): int {
        return PrestamoAlmacenDetalle::insertGetId([
            'id_prestamo_almacen' => $id_prestamo,
            'id_solicitud_reabastecimiento_detalle' => $id_solicitud_detalle_original,
            'cantidad_solicitada' => $cantidad_solicitada,
            'cantidad_solicitada_base' => $cantidad_solicitada_base,
            'cantidad_prestada' => 0,
            'cantidad_prestada_base' => 0,
            'cantidad_repuesta' => 0,
            'cantidad_repuesta_base' => 0,
            'comentario' => $comentario,
            'estado' => $estado,
        ]);
    }
}
