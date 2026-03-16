<?php

namespace App\Views\SolicitudesReabastecimientoAtencion\Data;

use App\Models\RequerimientoAlmacenDetalle;
use App\Models\RequerimientoAlmacenDetalleLog;
use App\Models\SolicitudReabastecimientoDetalle;
use App\Models\SolicitudReabastecimientoDetalleLog;
use Illuminate\Support\Facades\DB;

class SolicitudesDetalleData
{

    /**
     * Obtiene los detalles de una solicitud de reabastecimiento
     */
    public static function get_detalles_by_solicitud(
        int $id_solicitud
    ) {
        $sql = '
        SELECT DISTINCT
            srd.id AS id_solicitud_detalle,
            CONCAT(emp.nombre, " ", emp.apellido) AS empleado_atencion,
            pr.id AS id_producto,
            pr.id_unidad_medida_base,
            srd.id_unidad_medida as id_unidad_medida_sol, 
            pr.nombre AS producto,
            pr.stock_minimo,
            unib.abreviatura AS unidad_medida_base_abv,
            uni.abreviatura AS unidad_medida_sol_abv,
            srd.contenido_por_presentacion,
            srd.cantidad_solicitada,
            srd.cantidad_solicitada_base,
            srd.cantidad_entregada,
            srd.cantidad_entregada_base,
            CASE 
                WHEN srd.cantidad_solicitada_base > 0 THEN 
                    ROUND(((srd.cantidad_entregada_base / srd.cantidad_solicitada_base) * 100 ), 0)
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
            srd.comentario,
            srd.comentario_decision,
            srd.estado
        FROM
            solicitud_reabastecimiento_detalle srd
        INNER JOIN producto pr ON pr.id = srd.id_producto
        LEFT JOIN unidad_medida unib ON unib.id = pr.id_unidad_medida_base
        LEFT JOIN unidad_medida uni ON uni.id = srd.id_unidad_medida
        LEFT JOIN empleado emp ON emp.id = srd.id_empleado_atencion
        LEFT JOIN solicitud_reabastecimiento src on src.id = srd.id_solicitud_reabastecimiento
        LEFT JOIN almacen alm on alm.id = src.id_almacen_solicitante
        WHERE 1=1
        ';

        $params = [];
        $sql .= ' AND srd.id_solicitud_reabastecimiento = :id_solicitud';
        $params['id_solicitud'] = $id_solicitud;

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
                srdl.id AS id_solicitud_detalle_log,
                CASE
                    WHEN srdl.id_empleado IS NOT NULL THEN (
                        SELECT CONCAT(emp.nombre, " ", emp.apellido)
                        FROM empleado emp
                        WHERE emp.id = srdl.id_empleado
                    )
                    ELSE NULL
                END AS empleado,
                srdl.descripcion,
                srdl.created_at,
                srdl.estado
            FROM
                solicitud_reabastecimiento_detalle_log srdl
            WHERE
                srdl.id_solicitud_reabastecimiento_detalle = :id_detalle
            ORDER BY srdl.created_at
        ', ["id_detalle" => $id_detalle]);
    }

    /**
     * Inserta un log de trazabilidad para un detalle
     */
    public static function insert_detalle_log(int $id_detalle, int $id_empleado, string $descripcion, string $estado)
    {
        return SolicitudReabastecimientoDetalleLog::insertGetId([
            'id_solicitud_reabastecimiento_detalle' => $id_detalle,
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

        return SolicitudReabastecimientoDetalle::where('id', $id_detalle)
            ->update($updateData);
    }


    /**
     * Incrementar cantidades entregadas en el detalle del requerimiento
     */
    public static function increment_detalle_entregado(int $id_detalle, float $cantidad_sol, float $cantidad_base)
    {
        return SolicitudReabastecimientoDetalle::where('id', $id_detalle)
            ->incrementEach([
                'cantidad_entregada' => $cantidad_sol,
                'cantidad_entregada_base' => $cantidad_base
            ]);
    }


    public static function get_id_solicitud_by_detalle(int $id_detalle)
    {
        return SolicitudReabastecimientoDetalle::select('id_solicitud_reabastecimiento')
            ->where('id', $id_detalle)
            ->first();
    }

    /**
     * Obtener detalle por id
     */
    public static function get_detalle_by_id(int $id_detalle)
    {
        return SolicitudReabastecimientoDetalle::select('cantidad_entregada_base', 'cantidad_solicitada_base')
            ->where('id', $id_detalle)
            ->first();
    }
}
