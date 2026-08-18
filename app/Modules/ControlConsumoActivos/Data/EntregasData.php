<?php

namespace App\Modules\ControlConsumoActivos\Data;

use App\Models\RequerimientoAlmacenEntregaDetalle;
use App\Models\RequerimientoAlmacenEntregaDetalleConsumo;
use Illuminate\Support\Facades\DB;

class EntregasData
{
    /**
     * Listar todos los detalles de la entrega de requerimientos filtrados por periodo.
     * Incluye datos de costo (snapshot del detalle + fallback a lote / OC), empleado entrega/recibe,
     * categoria, marca del activo fijo (si aplica) y datos del solicitante.
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
            rq.id_contratista_solicitante,
            CASE
            	WHEN rq.id_empleado_solicitante IS NOT NULL THEN CONCAT(emp_sol.nombre, " ", emp_sol.apellido)
                WHEN rq.id_contratista_solicitante IS NOT NULL THEN CONCAT(ctr_sol.nombre, " ", ctr_sol.apellido)
                ELSE NULL
            END AS solicitante,
            emp_sol.id_cargo as id_cargo_solicitante,
            cargo_sol.nombre as cargo_solicitante,
            emp_sol.id_empresa as id_empresa_solicitante,
            emp_sol.id_mina as id_mina_solicitante,

            -- para que mina y labor se solicito
            lb.id_mina,
            mn.nombre as mina,
            rq.id_labor,
            lb.nombre as labor,

            -- que almacen atendio
            rq.id_almacen_destino,
            alm.nombre as almacen_destino,

            -- el produco que pidio y su unidad base
            pr.id as id_producto,
            pr.nombre as producto,
            cat.id as id_categoria,
            cat.nombre as categoria,
            pr.id_unidad_medida_base,
            umb.nombre as unidad_medida_base,
            umb.abreviatura as unidad_medida_base_abv,
            cat.es_consumible,
            cat.clasificacion_bien as tipo_bien,
            pr.moneda,

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
            ent.correlativo as correlativo_entrega,
            ent.id_empleado_entrega,
            CONCAT(emp_ent.nombre, " ", emp_ent.apellido) as empleado_entrega,
            ent.id_empleado_recibe,
            CONCAT(emp_rec.nombre, " ", emp_rec.apellido) as empleado_recibe,

            -- cantidades entregadas segun la unidad base y la unidad del requerimiento
            entd.cantidad_base as cantidad_entregada_base,
            entd.cantidad_requerimiento as cantidad_entregada_req,

            -- destino original confirmado en la entrega
            entd.para_mantenimiento,
            entd.para_produccion,
            entd.id_activo_fijo_destino,
            entd.id_lote_mineral,
            act_dest.correlativo as correlativo_activo_fijo_destino,
            lm_dest.codigo as codigo_lote_mineral_destino,
            lm_dest.id_mina as id_mina_lote_destino,
            mn_dest.nombre as mina_lote_destino,
            lm_dest.id_labor as id_labor_lote_destino,
            lb_dest.nombre as labor_lote_destino,
            pr.para_mantenimiento as producto_para_mantenimiento,

            -- lote del que provino la entrega (si fue un lote)
            entd.id_lote_producto,
            entd.id_activo_fijo,
            lp.correlativo as correlativo_lote_producto,
            lp.descripcion as descripcion_lote_producto,
            lp.serie_factura_compra,
            lp.numero_factura_compra,
            lp.fecha_hora_ingreso as fecha_ingreso_lote,
            af_entrega.correlativo as correlativo_activo_fijo_entrega,
            af_entrega.modelo as modelo_activo_fijo_entrega,
            af_entrega.costo_compra as costo_compra_activo_fijo_entrega,
            af_entrega.id_marca as id_marca_activo_fijo_entrega,
            mr_af.nombre as marca_activo_fijo_entrega,

            -- cantidad consumida
            (
                SELECT
                	COALESCE(SUM(cns.cantidad_base_consumida), 0)
                FROM requerimiento_almacen_entrega_detalle_consumo cns
                WHERE entd.id = cns.id_requerimiento_almacen_entrega_detalle
            ) as cantidad_consumida_base,

            -- COSTOS: snapshot del detalle, fallback a lote, fallback a OC detalle
            entd.costo_promedio_base as costo_snapshot_detalle,
            entd.costo_unidad_lote as costo_unidad_lote_detalle,
            entd.subtotal as subtotal_detalle,
            lp.costo_promedio_base as costo_promedio_lote,
            lp.costo_por_unidad as costo_por_unidad_lote,
            ocd.precio_unitario_base as precio_unitario_base_oc,

            -- costo unitario resuelto (regla de prioridad) y su origen
            COALESCE(
                NULLIF(entd.costo_promedio_base, 0),
                NULLIF(lp.costo_promedio_base, 0),
                NULLIF(lp.costo_por_unidad, 0),
                NULLIF(ocd.precio_unitario_base, 0),
                0
            ) as costo_unitario_base,
            CASE
                WHEN NULLIF(entd.costo_promedio_base, 0) IS NOT NULL THEN "snapshot_detalle"
                WHEN NULLIF(lp.costo_promedio_base, 0) IS NOT NULL THEN "lote_promedio"
                WHEN NULLIF(lp.costo_por_unidad, 0) IS NOT NULL THEN "lote_compra"
                WHEN NULLIF(ocd.precio_unitario_base, 0) IS NOT NULL THEN "oc_detalle"
                ELSE "sin_costo"
            END as origen_costo_unitario

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
        LEFT JOIN empleado emp_sol on emp_sol.id = rq.id_empleado_solicitante
        LEFT JOIN empleado ctr_sol on ctr_sol.id = rq.id_contratista_solicitante
        LEFT JOIN cargo cargo_sol on cargo_sol.id = emp_sol.id_cargo
        LEFT JOIN labor lb on lb.id = rq.id_labor
        LEFT JOIN mina mn on mn.id = lb.id_mina
        INNER JOIN almacen alm on alm.id = rq.id_almacen_destino

        -- empleados de la entrega
        LEFT JOIN empleado emp_ent on emp_ent.id = ent.id_empleado_entrega
        LEFT JOIN empleado emp_rec on emp_rec.id = ent.id_empleado_recibe

        -- destinos confirmados
        LEFT JOIN activo_fijo act_dest ON act_dest.id = entd.id_activo_fijo_destino
        LEFT JOIN lote_mineral lm_dest ON lm_dest.id = entd.id_lote_mineral
        LEFT JOIN mina mn_dest ON mn_dest.id = lm_dest.id_mina
        LEFT JOIN labor lb_dest ON lb_dest.id = lm_dest.id_labor

        -- lote del que provino la entrega
        LEFT JOIN lote_producto lp ON lp.id = entd.id_lote_producto
        LEFT JOIN orden_compra_detalle ocd ON ocd.id = lp.id_orden_compra_detalle

        -- activo fijo entregado (si la entrega fue de un AF)
        LEFT JOIN activo_fijo af_entrega ON af_entrega.id = entd.id_activo_fijo
        LEFT JOIN marca mr_af ON mr_af.id = af_entrega.id_marca

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
