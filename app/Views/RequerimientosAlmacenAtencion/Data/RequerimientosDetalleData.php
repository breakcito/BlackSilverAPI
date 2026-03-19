<?php

namespace App\Views\RequerimientosAlmacenAtencion\Data;

use App\Models\RequerimientoAlmacenDetalle;
use App\Models\RequerimientoAlmacenDetalleLog;
use Illuminate\Support\Facades\DB;

class RequerimientosDetalleData
{

    /**
     * Obtiene los detalles de un requerimiento de almacen
     */
    public static function get_detalles_by_requerimiento(
        int $id_requerimiento
    ) {
        // 1. Definimos la base de la consulta (sin WHERE ni ORDER BY aún)
        $sql = '
        SELECT DISTINCT
            rad.id AS id_requerimiento_almacen_detalle,
            CONCAT(emp.nombre, " ", emp.apellido) AS empleado_atencion,
            pr.id AS id_producto,
            pr.id_unidad_medida_base,
            rad.id_unidad_medida as id_unidad_medida_req, 
            pr.nombre AS producto,
            pr.stock_minimo,
            unib.abreviatura AS unidad_medida_base_abv,
            uni.abreviatura AS unidad_medida_abv,
            rad.contenido_por_presentacion,
            rad.cantidad_solicitada,
            rad.cantidad_solicitada_base,
            rad.cantidad_entregada,
            rad.cantidad_entregada_base,
            CASE 
                WHEN rad.cantidad_solicitada_base > 0 THEN 
                    ROUND(((rad.cantidad_entregada_base / rad.cantidad_solicitada_base) * 100 ), 0)
                ELSE 0 
            END AS porcentaje_progreso,
            (
                SELECT
                    SUM(lot.stock_actual_base)
                FROM
                    lote_producto lot
                WHERE
                    lot.id_producto = pr.id AND 
                    lot.estado = "Activo" AND 
                    lot.id_almacen = alm.id
            ) as stock_disponible,
            rad.id_producto_destino,
            p_dest.nombre AS producto_destino,
            rad.comentario,
            rad.comentario_decision,
            rad.estado
        FROM
            requerimiento_almacen_detalle rad
        INNER JOIN producto pr ON pr.id = rad.id_producto
        LEFT JOIN producto p_dest ON p_dest.id = rad.id_producto_destino
        LEFT JOIN unidad_medida unib ON unib.id = pr.id_unidad_medida_base
        LEFT JOIN unidad_medida uni ON uni.id = rad.id_unidad_medida
        LEFT JOIN empleado emp ON emp.id = rad.id_empleado_atencion
        LEFT JOIN requerimiento_almacen req on req.id = rad.id_requerimiento_almacen
        LEFT JOIN almacen alm on alm.id = req.id_almacen_destino
        WHERE 1=1
        ';

        $params = [];
        $sql .= ' AND rad.id_requerimiento_almacen = :id_requerimiento';
        $params['id_requerimiento'] = $id_requerimiento;

        $sql .= ' ORDER BY pr.nombre';

        return DB::select($sql, $params);
    }

    /**
     * Obtiene los logs de trazabilidad de un detalle
     */
    public static function get_detalle_logs(int $id_detalle)
    {
        return DB::select('
            SELECT DISTINCT
                trz.id AS id_requerimiento_almacen_detalle_log,
                CASE
                    WHEN trz.id_empleado IS NOT NULL THEN (
                        SELECT CONCAT(emp.nombre, " ", emp.apellido)
                        FROM empleado emp
                        WHERE emp.id = trz.id_empleado
                    )
                    ELSE NULL
                END AS empleado,
                trz.descripcion,
                trz.created_at,
                trz.estado
            FROM
                requerimiento_almacen_detalle_log trz
            WHERE
                trz.id_requerimiento_almacen_detalle = :id_detalle
            ORDER BY trz.created_at
        ', ["id_detalle" => $id_detalle]);
    }

    /**
     * Inserta un log de trazabilidad para un detalle
     */
    public static function insert_detalle_log(int $id_detalle, int $id_empleado, string $descripcion, string $estado)
    {
        return RequerimientoAlmacenDetalleLog::insertGetId([
            'id_requerimiento_almacen_detalle' => $id_detalle,
            'id_empleado' => $id_empleado,
            'descripcion' => $descripcion,
            'estado' => $estado,
            'created_at' => now()
        ]);
    }

    /**
     * Actualiza el estado de un detalle de requerimiento
     */
    public static function update_detalle_estado(int $id_detalle, string $estado, int $id_empleado, ?string $comentario = null)
    {
        $updateData = [
            'estado' => $estado,
            'id_empleado_atencion' => $id_empleado
        ];

        if ($comentario !== null) {
            $updateData['comentario_decision'] = $comentario;
        }

        return RequerimientoAlmacenDetalle::where('id', $id_detalle)
            ->update($updateData);
    }


    /**
     * Incrementar cantidades entregadas en el detalle del requerimiento
     */
    public static function increment_detalle_entregado(int $id_detalle, float $cantidad_req, float $cantidad_base)
    {
        return RequerimientoAlmacenDetalle::where('id', $id_detalle)
            ->incrementEach([
                'cantidad_entregada' => $cantidad_req,
                'cantidad_entregada_base' => $cantidad_base
            ]);
    }


    public static function get_id_requerimiento_by_detalle(int $id_detalle)
    {
        return DB::selectOne('
            SELECT
                rad.id_requerimiento_almacen
            FROM
                requerimiento_almacen_detalle rad
            WHERE
                rad.id = :id_detalle
        ', ["id_detalle" => $id_detalle]);
    }

    /**
     * Obtener detalle por id
     */
    public static function get_detalle_by_id(int $id_detalle)
    {
        return RequerimientoAlmacenDetalle::select('cantidad_entregada_base', 'cantidad_solicitada_base')
            ->where('id', $id_detalle)
            ->first();
    }
}
