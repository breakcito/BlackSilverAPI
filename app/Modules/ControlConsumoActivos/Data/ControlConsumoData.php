<?php

namespace App\Modules\ControlConsumoActivos\Data;


use App\Models\RequerimientoAlmacenEntregaDetalleConsumo;
use App\Shared\Enums\RequerimientoAlmacen\EstadoConsumoDetalleEntregaReq;
use Illuminate\Support\Facades\DB;

class ControlConsumoData
{
    /**
     * Obtener consumos por detalle(s) de entrega o por ID de consumo específico.
     * Incluye datos de costos (snapshot del detalle con fallback a lote / OC), marca/modelo/costo del
     * activo fijo consumidor, mina/labor del lote mineral destino, cargo del empleado registro,
     * y todas las labores destino como lista de nombres.
     *
     * @param array<int>|null $id_detalle_entrega
     */
    public static function get_consumos(
        int|array|null $id_detalle_entrega = null,
        ?int $id_consumo = null
    ): array|object|null {
        $sql = '
        SELECT
            c.id as id_consumo,
            c.id_requerimiento_almacen_entrega_detalle,
            entd.id_requerimiento_almacen_entrega,
            rad.id_requerimiento_almacen,
            rq.correlativo as correlativo_requerimiento,

            c.id_activo_fijo_consumidor,
            act.correlativo as correlativo_activo_fijo_consumidor,
            act.modelo as modelo_activo_fijo_consumidor,
            act.costo_compra as costo_compra_activo_fijo_consumidor,
            act.id_marca as id_marca_activo_fijo_consumidor,
            mr_af.nombre as marca_activo_fijo_consumidor,

            c.id_labor_destino,
            lb.nombre as labor,

            c.id_empleado_registro,
            CONCAT(emp.nombre, " ", emp.apellido) as empleado_registro,
            emp.id_cargo as id_cargo_registro,
            cargo_reg.nombre as cargo_registro,

            c.cantidad_base_consumida,
            c.fecha_hora_consumo,
            c.comentario_consumo,
            c.created_at,
            c.estado,
            c.id_mantenimiento,
            c.id_lote_mineral,
            c.para_mantenimiento,
            c.para_produccion,
            lm.codigo as codigo_lote_mineral,
            lm.id_mina as id_mina_lote_mineral,
            mn_lm.nombre as mina_lote_mineral,
            lm.id_labor as id_labor_lote_mineral,
            lb_lm.nombre as labor_lote_mineral,

            -- costo unitario resuelto (snapshot del detalle con fallback a lote / OC) por cada consumo
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
            END as origen_costo_unitario,

            -- costo total del consumo = cantidad_base_consumida * costo_unitario_base
            ROUND(c.cantidad_base_consumida * COALESCE(
                NULLIF(entd.costo_promedio_base, 0),
                NULLIF(lp.costo_promedio_base, 0),
                NULLIF(lp.costo_por_unidad, 0),
                NULLIF(ocd.precio_unitario_base, 0),
                0
            ), 4) as costo_total_consumo

        FROM requerimiento_almacen_entrega_detalle_consumo c
        INNER JOIN requerimiento_almacen_entrega_detalle entd ON entd.id = c.id_requerimiento_almacen_entrega_detalle
        INNER JOIN requerimiento_almacen_detalle rad ON rad.id = entd.id_requerimiento_almacen_detalle
        INNER JOIN requerimiento_almacen rq ON rq.id = rad.id_requerimiento_almacen
        LEFT JOIN empleado emp ON emp.id = c.id_empleado_registro
        LEFT JOIN cargo cargo_reg ON cargo_reg.id = emp.id_cargo
        LEFT JOIN lote_mineral lm ON lm.id = c.id_lote_mineral
        LEFT JOIN mina mn_lm ON mn_lm.id = lm.id_mina
        LEFT JOIN labor lb_lm ON lb_lm.id = lm.id_labor
        LEFT JOIN activo_fijo act ON act.id = c.id_activo_fijo_consumidor
        LEFT JOIN marca mr_af ON mr_af.id = act.id_marca
        LEFT JOIN labor lb ON lb.id = c.id_labor_destino
        LEFT JOIN lote_producto lp ON lp.id = entd.id_lote_producto
        LEFT JOIN orden_compra_detalle ocd ON ocd.id = lp.id_orden_compra_detalle
        WHERE 1=1
        ';

        $params = [];

        if ($id_consumo !== null) {
            $sql .= ' AND c.id = :id_consumo';
            $params['id_consumo'] = $id_consumo;
            return DB::selectOne($sql, $params);
        }

        if ($id_detalle_entrega !== null) {
            if (is_array($id_detalle_entrega)) {
                if (empty($id_detalle_entrega)) {
                    return [];
                }
                $ids = array_map('intval', $id_detalle_entrega);
                $sql .= ' AND c.id_requerimiento_almacen_entrega_detalle IN (' . implode(',', $ids) . ')';
            } else {
                $sql .= ' AND c.id_requerimiento_almacen_entrega_detalle = :id_detalle_entrega';
                $params['id_detalle_entrega'] = $id_detalle_entrega;
            }
        }

        $sql .= ' ORDER BY c.fecha_hora_consumo ASC';

        return DB::select($sql, $params);
    }


    /**
     * Registrar un nuevo consumo en la base de datos.
     */
    public static function crear_consumo(
        int $id_requerimiento_almacen_entrega_detalle,
        int $id_empleado_registro,
        float $cantidad_base_consumida,
        string $fecha_hora_consumo,
        ?string $comentario_consumo,
        EstadoConsumoDetalleEntregaReq $estado,
        ?int $id_activo_fijo_consumidor = null,
        ?int $id_labor_destino = null,
        ?int $id_mantenimiento = null,
        ?int $id_lote_mineral = null,
        bool $para_mantenimiento = false,
        bool $para_produccion = false
    ): int {
        return RequerimientoAlmacenEntregaDetalleConsumo::crear_consumo(
            id_requerimiento_almacen_entrega_detalle: $id_requerimiento_almacen_entrega_detalle,
            id_empleado_registro: $id_empleado_registro,
            cantidad_base_consumida: $cantidad_base_consumida,
            fecha_hora_consumo: $fecha_hora_consumo,
            comentario_consumo: $comentario_consumo,
            estado: $estado,
            id_activo_fijo_consumidor: $id_activo_fijo_consumidor,
            id_labor_destino: $id_labor_destino,
            id_mantenimiento: $id_mantenimiento,
            id_lote_mineral: $id_lote_mineral,
            para_mantenimiento: $para_mantenimiento,
            para_produccion: $para_produccion,
        );
    }
}
