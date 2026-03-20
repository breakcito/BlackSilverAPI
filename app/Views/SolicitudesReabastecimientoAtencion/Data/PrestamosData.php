<?php

namespace App\Views\SolicitudesReabastecimientoAtencion\Data;

use Illuminate\Support\Facades\DB;
use App\Models\PrestamoAlmacen;

class PrestamosData
{
    public static function get_prestamos_por_solicitud(int $id_solicitud_reabastecimiento)
    {
        $sql = "
        SELECT
            pa.id,
            pa.id_solicitud_reabastecimiento,
            pa.id_almacen_prestamista,
            pa.correlativo,
            pa.numero_correlativo,
            pa.fecha_hora_prestamo,
            pa.fecha_limite_devolucion,
            pa.created_at,
            pa.estado,
            a.nombre AS almacen_prestamista,
            CONCAT(e.nombre, ' ', e.apellido) AS registrado_por
        FROM
            prestamo_almacen pa
        INNER JOIN almacen a ON a.id = pa.id_almacen_prestamista
        INNER JOIN empleado e ON e.id = pa.id_empleado_registro
        WHERE
            pa.id_solicitud_reabastecimiento = :id_solicitud
        ORDER BY
            pa.created_at DESC
        ";

        return DB::select($sql, ['id_solicitud' => $id_solicitud_reabastecimiento]);
    }

    public static function get_prestamo_por_id(int $id_prestamo)
    {
        $sql = "
        SELECT
            pa.*,
            a.nombre AS almacen_prestamista,
            CONCAT(e.nombre, ' ', e.apellido) AS registrado_por
        FROM
            prestamo_almacen pa
        INNER JOIN almacen a ON a.id = pa.id_almacen_prestamista
        INNER JOIN empleado e ON e.id = pa.id_empleado_registro
        WHERE
            pa.id = :id
        ";

        return DB::selectOne($sql, ['id' => $id_prestamo]);
    }

    public static function crear_prestamo_cabecera(
        int $id_solicitud_reabastecimiento,
        int $id_almacen_prestamista,
        int $id_empleado_registro,
        string $correlativo,
        int $numero_correlativo,
        string $fecha_hora_prestamo,
        string $fecha_limite_devolucion,
        string $estado
    ): int {
        return PrestamoAlmacen::insertGetId([
            'id_solicitud_reabastecimiento' => $id_solicitud_reabastecimiento,
            'id_almacen_prestamista' => $id_almacen_prestamista,
            'id_empleado_registro' => $id_empleado_registro,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'fecha_hora_prestamo' => $fecha_hora_prestamo,
            'fecha_limite_devolucion' => $fecha_limite_devolucion,
            'created_at' => now(),
            'estado' => $estado,
        ]);
    }

    public static function get_almacenes_con_stock_por_producto(int $id_producto, int $id_almacen_excluido)
    {
        $sql = "
        SELECT
            a.id AS id_almacen,
            a.nombre AS nombre_almacen,
            SUM(lp.stock_actual_base) AS stock_actual_base,
            um_base.abreviatura AS unidad_medida_base
        FROM
            lote_producto lp
        INNER JOIN almacen a ON a.id = lp.id_almacen
        INNER JOIN producto p ON p.id = lp.id_producto
        INNER JOIN unidad_medida um_base ON um_base.id = p.id_unidad_medida_base
        WHERE
            lp.id_producto = :id_producto
            AND a.id != :id_excluido
            AND a.es_principal = 0
            AND lp.estado = 'Activo'
        GROUP BY
            a.id, a.nombre, um_base.abreviatura
        HAVING
            SUM(lp.stock_actual_base) > 0
        ORDER BY
            a.nombre ASC
        ";

        return DB::select($sql, [
            'id_producto' => $id_producto,
            'id_excluido' => $id_almacen_excluido
        ]);
    }

    public static function get_lotes_disponibles_por_almacen_y_producto(int $id_producto, int $id_almacen)
    {
        $sql = "
        SELECT
            lp.id AS id_lote,
            lp.descripcion AS lote,
            lp.correlativo,
            lp.numero_correlativo,
            lp.stock_actual,
            lp.stock_actual_base,
            um.abreviatura AS unidad_medida,
            lp.fecha_vencimiento
        FROM
            lote_producto lp
        INNER JOIN unidad_medida um ON um.id = lp.id_unidad_medida
        WHERE
            lp.id_producto = :id_producto
            AND lp.id_almacen = :id_almacen
            AND lp.stock_actual > 0
            AND lp.estado = 'Activo'
        ORDER BY
            lp.fecha_vencimiento ASC
        ";

        return DB::select($sql, [
            'id_producto' => $id_producto,
            'id_almacen' => $id_almacen
        ]);
    }
}
