<?php

namespace App\Views\PrestamosAlmacenAtencion\Data;

use App\Models\PrestamoAlmacenDetalle;
use App\Models\PrestamoAlmacenDetalleLog;
use Illuminate\Support\Facades\DB;

class PrestamosDetalleData
{
    /**
     * Obtiene el detalle de los ítems de un préstamo específico.
     */
    public static function get_detalles_prestamo(int $id_prestamo): array
    {
        return DB::select('
            SELECT
                pad.id AS id_prestamo_detalle,
                pad.id_solicitud_reabastecimiento_detalle,
                pad.cantidad_solicitada,
                pad.cantidad_solicitada_base,
                COALESCE(pad.cantidad_prestada_base, 0) AS cantidad_prestada_base,
                COALESCE(pad.cantidad_prestada_base / NULLIF(srd.contenido_por_presentacion, 0), 0) AS cantidad_prestada,
                pad.comentario,
                pad.estado,
                srd.id_producto,
                prod.nombre AS producto,
                um.nombre AS unidad_medida,
                um.abreviatura AS unidad_medida_abv,
                um_base.abreviatura AS unidad_medida_base_abv,
                srd.id_unidad_medida,
                srd.contenido_por_presentacion
            FROM
                prestamo_almacen_detalle pad
            INNER JOIN solicitud_reabastecimiento_detalle srd ON srd.id = pad.id_solicitud_reabastecimiento_detalle
            INNER JOIN producto prod ON prod.id = srd.id_producto
            INNER JOIN unidad_medida um ON um.id = srd.id_unidad_medida
            INNER JOIN unidad_medida um_base ON um_base.id = prod.id_unidad_medida_base
            WHERE pad.id_prestamo_almacen = :id_prestamo
            ORDER BY prod.nombre ASC
        ', ['id_prestamo' => $id_prestamo]);
    }

    /**
     * Cambiar el estado de un detalle de préstamo e insertar registro de trazabilidad.
     */
    public static function update_detalle_estado(
        int $id_prestamo_detalle,
        string $nuevo_estado,
        ?string $comentario = null
    ): void {
        PrestamoAlmacenDetalle::where("id", $id_prestamo_detalle)
            ->update(["estado" => $nuevo_estado]);
    }

    /**
     * Inserta un log de trazabilidad para un ítem de préstamo.
     */
    public static function insert_detalle_log(
        int $id_prestamo_detalle,
        int $id_empleado,
        string $estado,
        ?string $comentario = null
    ): void {
        PrestamoAlmacenDetalleLog::insert([
            "id_prestamo_almacen_detalle" => $id_prestamo_detalle,
            "id_empleado" => $id_empleado,
            "estado" => $estado,
            "descripcion" => $comentario,
            "created_at" => now()
        ]);
    }

    /**
     * Obtener el historial de trazabilidad de un ítem de préstamo.
     */
    public static function get_detalle_logs(int $id_prestamo_detalle): array
    {
        return DB::select('
            SELECT
                log.estado,
                log.descripcion AS comentario,
                log.created_at,
                CONCAT(e.nombre, " ", e.apellido) AS nombre_empleado,
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

    /**
     * Obtiene el ID del préstamo por un ID de detalle.
     */
    public static function get_id_prestamo_by_detalle(int $id_detalle)
    {
        return PrestamoAlmacenDetalle::select('id_prestamo_almacen')
            ->where('id', $id_detalle)
            ->first();
    }
}
