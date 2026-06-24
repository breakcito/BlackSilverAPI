<?php

namespace App\Modules\ControlConsumoActivos\Data;

use App\Models\RequerimientoAlmacenEntregaDetalle;
use App\Models\RequerimientoAlmacenEntregaDetalleConsumo;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalle;
use Illuminate\Support\Facades\DB;

class EntregasData
{
    /**
     * Listar todos los detalles de la entrega de requerimientos filtrados por mina, almacen y periodo.
     */
    public static function get_reporte(
        int $mes,
        int $yearcito
    ): array {
        $sql = '
        SELECT
            entd.id as id_entrega_requerimiento_detalle,
            
            -- de que requerimiento
            rad.id_requerimiento_almacen,
            rq.correlativo as correlativo_requerimiento,
            rq.created_at as fecha_requerimiento,
            rq.es_auditable,
            
            -- quien solicito ese requerimiento
            rq.id_empleado_solicitante,
            CONCAT(emp_sol.nombre, " ", emp_sol.apellido) as empleado_solicitante,
            
            -- para que mina se solicito
            rq.id_mina,
            mn.nombre as mina,
            
            -- que almacen atendio
            rq.id_almacen_destino,
            alm.nombre as almacen_destino,
            
            -- el produco que pidio y su unidad base
            pr.nombre as producto,
            pr.id_unidad_medida_base,
            umb.nombre as unidad_medida_base,
            umb.abreviatura as unidad_medida_base_abv,
            cat.es_consumible,
            cat.clasificacion_bien as tipo_bien,
            
            -- en que unidad de medida hizo el requerimiento
            rad.id_unidad_medida as id_unidad_medida_req,
            umr.nombre as unidad_medida_req,
            umr.abreviatura as unidad_medida_req_abv,
            
            -- cantidades solicitadas segun la unidad base y la unidad del requerimiento
            rad.cantidad_solicitada_base,
            rad.cantidad_solicitada,
            
            -- datos de la entrega
            entd.id_requerimiento_almacen_entrega,
            ent.fecha_hora_entrega,
            
            -- cantidades entregadas segun la unidad base y la unidad del requerimiento
            entd.cantidad_base as cantidad_entregada_base,
            entd.cantidad_requerimiento as cantidad_entregada_req,
            
            -- destino original confirmado en la entrega
            entd.para_mantenimiento,
            entd.para_produccion,
            entd.id_activo_fijo_destino,
            entd.id_lote_mineral,
            act_dest.correlativo as correlativo_activo_fijo_destino,
            lm_dest.correlativo as correlativo_lote_mineral_destino,
            pr.para_mantenimiento as producto_para_mantenimiento,

            -- cantidad consumida
            (
                SELECT
                	COALESCE(SUM(cns.cantidad_base_consumida), 0)
                FROM requerimiento_almacen_entrega_detalle_consumo cns
                WHERE entd.id = cns.id_requerimiento_almacen_entrega_detalle
            ) as cantidad_consumida_base
            
        FROM requerimiento_almacen_entrega_detalle entd
        INNER JOIN requerimiento_almacen_entrega ent on ent.id = entd.id_requerimiento_almacen_entrega
        INNER JOIN requerimiento_almacen_detalle rad on rad.id = entd.id_requerimiento_almacen_detalle
        
        -- para saber lo que pidio
        INNER JOIN producto pr on pr.id = rad.id_producto
        INNER JOIN categoria cat on cat.id = pr.id_categoria
        INNER JOIN unidad_medida umb on umb.id = pr.id_unidad_medida_base
        INNER JOIN unidad_medida umr on umr.id = rad.id_unidad_medida
 
        -- para saber quien y de donde se pidio 
        INNER JOIN requerimiento_almacen rq on rq.id = rad.id_requerimiento_almacen
        INNER JOIN empleado emp_sol on emp_sol.id = rq.id_empleado_solicitante
        INNER JOIN mina mn on mn.id = rq.id_mina
        INNER JOIN almacen alm on alm.id = rq.id_almacen_destino

        -- destinos confirmados
        LEFT JOIN activo_fijo act_dest ON act_dest.id = entd.id_activo_fijo_destino
        LEFT JOIN lote_mineral lm_dest ON lm_dest.id = entd.id_lote_mineral
 
        WHERE 
            -- filtro por periodo
            MONTH(rq.created_at) = :mes AND
            YEAR(rq.created_at) = :yearcito
        ';

        $params = [
            'mes' => $mes,
            'yearcito' => $yearcito,
        ];

        return DB::select($sql, $params);
    }


    /**
     * Obtener un detalle de entrega de requerimiento por ID.
     */
    public static function get_entrega_detalle(int $id_detalle): ?RequerimientoAlmacenEntregaDetalle
    {
        return RequerimientoAlmacenEntregaDetalle::find($id_detalle);
    }


    /**
     * Obtener la suma total consumida de un detalle de entrega.
     */
    public static function get_consumido_total_detalle(int $id_detalle): float
    {
        return (float) RequerimientoAlmacenEntregaDetalleConsumo::where('id_requerimiento_almacen_entrega_detalle', $id_detalle)
            ->sum('cantidad_base_consumida');
    }
}
