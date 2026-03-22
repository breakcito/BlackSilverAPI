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
        ?string $fecha_limite_devolucion,
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

    public static function get_almacenes_con_stock_multiple_productos(array $ids_productos, int $id_almacen_excluido)
    {
        $totalIds = count(array_unique($ids_productos));
        
        return DB::table('lote_producto as lp')
            ->join('almacen as a', 'a.id', '=', 'lp.id_almacen')
            ->join('producto as p', 'p.id', '=', 'lp.id_producto')
            ->join('unidad_medida as um_base', 'um_base.id', '=', 'p.id_unidad_medida_base')
            ->select(
                'a.id as id_almacen',
                'a.nombre as nombre_almacen',
                'lp.id_producto',
                'p.nombre as nombre_producto',
                DB::raw('SUM(lp.stock_actual_base) as stock_actual_base'),
                'um_base.abreviatura as unidad_medida_base'
            )
            ->whereIn('lp.id_producto', $ids_productos)
            ->where('a.id', '!=', $id_almacen_excluido)
            ->where('a.es_principal', 0)
            ->where('lp.estado', 'Activo')
            ->whereIn('a.id', function($query) use ($ids_productos, $totalIds) {
                $query->select('id_almacen')
                    ->from('lote_producto')
                    ->whereIn('id_producto', $ids_productos)
                    ->where('estado', 'Activo')
                    ->where('stock_actual_base', '>', 0)
                    ->groupBy('id_almacen')
                    ->havingRaw('COUNT(DISTINCT id_producto) = ?', [$totalIds]);
            })
            ->groupBy('a.id', 'a.nombre', 'lp.id_producto', 'p.nombre', 'um_base.abreviatura')
            ->havingRaw('SUM(lp.stock_actual_base) > 0')
            ->orderBy('a.nombre')
            ->get()
            ->toArray();
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
