<?php

namespace App\Views\SolicitudesReabastecimientoAtencion\Data;

use App\Models\SolicitudReabastecimientoDetalle;
use App\Models\SolicitudReabastecimientoDetalleLog;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalle;
use Illuminate\Support\Facades\DB;

class SolicitudesDetalleData
{

    /**
     * Obtiene los detalles de una solicitud de reabastecimiento desde el 
     * punto de vista del area de logistica
     */
    public static function get_detalles_by_solicitud(
        int $id_solicitud
    ) {
        $sql = '
        SELECT DISTINCT
            srd.id AS id_solicitud_detalle,
            CONCAT(emp.nombre, " ", emp.apellido) AS empleado_atencion,
            -- 
            pr.id AS id_producto,
            pr.nombre AS producto,
            pr.stock_minimo,
            --
            -- segun la unidad base del producto
            pr.id_unidad_medida_base,
            unib.abreviatura AS unidad_medida_base_abv,
           	srd.cantidad_solicitada_base,
            srd.cantidad_entregada_base,
            -- 
            -- cuantas unidades base hay en una unidad del detalle de la solicitud
            srd.contenido_por_presentacion,
            -- 
            -- segun la unidad del detalle de la solicitud
            srd.id_unidad_medida as id_unidad_medida_sol, 
            uni.abreviatura AS unidad_medida_sol_abv,
            srd.cantidad_solicitada,
            srd.cantidad_entregada,
            -- 
            -- el progreso que tiene este detalle segun lo entregado hasta el momento
            CASE 
                WHEN srd.cantidad_solicitada_base > 0 THEN 
                    ROUND(((srd.cantidad_entregada_base / srd.cantidad_solicitada_base) * 100 ), 0)
                ELSE 0 
            END AS porcentaje_progreso,
            -- 
            -- devolver la cantidad de stock disponible base en los almacenes principales
            (
                SELECT
                    SUM(lot.stock_actual_base)
                FROM
                    lote_producto lot
                WHERE
                    lot.id_producto = pr.id AND 
                    lot.estado = "Activo" AND 
                	lot.stock_actual_base > 0 AND
            		(lot.fecha_vencimiento > NOW() OR lot.fecha_vencimiento IS NULL) AND
                    lot.id_almacen IN (
                        SELECT
                        	almp.id
                        FROM almacen almp
                        WHERE 
                        	almp.es_principal = 1 AND
                        	almp.estado = "Activo"
                    )
            ) as stock_disponible_base,
            -- 
            -- la cantidad total que se ha sido pedida en un prestamo
            (
                SELECT 
                	IFNULL(SUM(pad.cantidad_solicitada_base), 0)
                FROM prestamo_almacen_detalle pad
                INNER JOIN prestamo_almacen pa ON pa.id = pad.id_prestamo_almacen
                WHERE 
                	pad.id_solicitud_reabastecimiento_detalle = srd.id AND 
                	pa.estado NOT IN ("Rechazado") -- no se toma en cuenta los rechazados
            ) AS cantidad_prestada_total_base,
            -- 
            srd.comentario,
            srd.comentario_decision,
            srd.estado
        FROM
            solicitud_reabastecimiento_detalle srd
        LEFT JOIN empleado emp ON emp.id = srd.id_empleado_atencion
        INNER JOIN producto pr ON pr.id = srd.id_producto
        INNER JOIN unidad_medida unib ON unib.id = pr.id_unidad_medida_base
        INNER JOIN unidad_medida uni ON uni.id = srd.id_unidad_medida
        INNER JOIN solicitud_reabastecimiento src on src.id = srd.id_solicitud_reabastecimiento
        INNER JOIN almacen alm on alm.id = src.id_almacen_solicitante
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_solicitud !== null) {
            $sql .= ' AND srd.id_solicitud_reabastecimiento = :id_solicitud';
            $params['id_solicitud'] = $id_solicitud;
        }

        $sql .= ' ORDER BY pr.nombre';

        return DB::select($sql, $params);
    }

    /**
     * Obtiene los logs de trazabilidad de un detalle
     */
    public static function get_detalle_logs(int $id_detalle)
    {
        return SolicitudReabastecimientoDetalleLog::get_logs(id_solicitud_detalle: $id_detalle);
    }

    /**
     * Inserta un log de trazabilidad para un detalle
     */
    public static function insert_detalle_log(
        int $id_detalle,
        int $id_empleado,
        string $descripcion,
        EstadoSolicitudDetalle $estado
    ) {
        return SolicitudReabastecimientoDetalleLog::crear_log(
            id_solicitud_detalle: $id_detalle,
            id_empleado: $id_empleado,
            descripcion: $descripcion,
            estado: $estado
        );
    }

    /**
     * Actualiza el estado de un detalle de solicitud
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
     * Obtener detalle por id para procesos de entrega
     */
    public static function get_detalle_by_id(int $id_detalle)
    {
        return SolicitudReabastecimientoDetalle::select(
            'id',
            'id_requerimiento_almacen_detalle',
            'cantidad_entregada_base',
            'cantidad_solicitada_base'
        )
            ->where('id', $id_detalle)
            ->first();
    }

    /**
     * Obtener detalle por id simplificado para préstamos
     */
    public static function get_detalle_para_prestamo(int $id_detalle)
    {
        return SolicitudReabastecimientoDetalle::select(
            'id',
            'id_producto',
            'id_unidad_medida',
            'contenido_por_presentacion',
            'cantidad_solicitada',
            'cantidad_entregada'
        )
            ->where('id', $id_detalle)
            ->first();
    }
}
