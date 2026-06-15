<?php

namespace App\Modules\MantenimientoActivos\Data;

use App\Models\MantenimientoActivo;
use Illuminate\Support\Facades\DB;

class MantenimientoData
{
    /**
     * Listar mantenimientos realizados con filtros de periodo y opcionalmente activo fijo.
     */
    public static function get_mantenimientos(int $mes, int $yearcito, ?int $id_activo_fijo = null): array
    {
        $sql = '
        SELECT
            ma.id AS id_mantenimiento,
            ma.id_activo_fijo,
            act.correlativo AS correlativo_activo_fijo,
            act.codigo AS codigo_activo_fijo,
            prod.nombre AS producto_activo_fijo,
            ma.fecha_hora_mantenimiento,
            ma.observacion,
            ma.lugar_trabajo,
            ma.costo_mano_obra,
            ma.otros_gastos,
            ma.total_horas,
            ma.total_kilometros,
            ma.total_vueltas,
            ma.id_proveedor,
            prov.razon_social AS proveedor_razon_social,
            ma.id_empleado_ejecutor,
            CONCAT(ejec.nombre, " ", ejec.apellido) AS ejecutor_nombre,
            ma.id_empleado_supervisor,
            CONCAT(superv.nombre, " ", superv.apellido) AS supervisor_nombre,
            ma.evidencias
        FROM mantenimiento_activo ma
        INNER JOIN activo_fijo act ON act.id = ma.id_activo_fijo
        INNER JOIN producto prod ON prod.id = act.id_producto
        LEFT JOIN proveedor prov ON prov.id = ma.id_proveedor
        LEFT JOIN empleado ejec ON ejec.id = ma.id_empleado_ejecutor
        LEFT JOIN empleado superv ON superv.id = ma.id_empleado_supervisor
        WHERE MONTH(ma.fecha_hora_mantenimiento) = :mes 
          AND YEAR(ma.fecha_hora_mantenimiento) = :yearcito
        ';

        $params = [
            'mes' => $mes,
            'yearcito' => $yearcito,
        ];

        if ($id_activo_fijo !== null) {
            $sql .= ' AND ma.id_activo_fijo = :id_activo_fijo';
            $params['id_activo_fijo'] = $id_activo_fijo;
        }

        $sql .= ' ORDER BY ma.fecha_hora_mantenimiento DESC';

        return DB::select($sql, $params);
    }

    /**
     * Obtener productos despachados para mantenimiento asociados a un activo fijo
     * que tengan saldo restante por consumir.
     */
    public static function get_productos_despachados(int $id_activo_fijo): array
    {
        $sql = '
        SELECT
            raed.id AS id_entrega_detalle,
            raed.id_requerimiento_almacen_detalle,
            p.id AS id_producto,
            p.nombre AS producto,
            raed.cantidad_base,
            uni.abreviatura AS unidad_base_abv,
            (
                SELECT COALESCE(SUM(c.cantidad_base_consumida), 0)
                FROM requerimiento_almacen_entrega_detalle_consumo c
                WHERE c.id_requerimiento_almacen_entrega_detalle = raed.id
            ) AS consumido_base
        FROM requerimiento_almacen_entrega_detalle raed
        LEFT JOIN lote_producto lot ON lot.id = raed.id_lote_producto
        LEFT JOIN activo_fijo act ON act.id = raed.id_activo_fijo
        INNER JOIN producto p ON p.id = COALESCE(lot.id_producto, act.id_producto)
        INNER JOIN unidad_medida uni ON uni.id = p.id_unidad_medida_base
        WHERE raed.para_mantenimiento = 1
          AND raed.id_activo_fijo_destino = :id_activo_fijo
        ';

        $rawItems = DB::select($sql, ['id_activo_fijo' => $id_activo_fijo]);

        // Filtrar aquellos que tengan saldo disponible por consumir
        $items = [];
        foreach ($rawItems as $item) {
            $cant_base = (float) $item->cantidad_base;
            $consumido = (float) $item->consumido_base;
            $restante = $cant_base - $consumido;

            if (round($restante, 4) > 0) {
                $item->restante_base = $restante;
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Obtener consumos pendientes de asociar a un mantenimiento (que fueron registrados en el módulo de consumos)
     */
    public static function get_consumos_pendientes(int $id_activo_fijo): array
    {
        $sql = '
        SELECT
            c.id AS id_consumo,
            c.id_requerimiento_almacen_entrega_detalle AS id_entrega_detalle,
            c.cantidad_base_consumida,
            c.fecha_hora_consumo,
            c.comentario_consumo,
            p.id AS id_producto,
            p.nombre AS producto,
            uni.abreviatura AS unidad_base_abv
        FROM requerimiento_almacen_entrega_detalle_consumo c
        INNER JOIN requerimiento_almacen_entrega_detalle raed ON raed.id = c.id_requerimiento_almacen_entrega_detalle
        LEFT JOIN lote_producto lot ON lot.id = raed.id_lote_producto
        LEFT JOIN activo_fijo act ON act.id = raed.id_activo_fijo
        INNER JOIN producto p ON p.id = COALESCE(lot.id_producto, act.id_producto)
        INNER JOIN unidad_medida uni ON uni.id = p.id_unidad_medida_base
        WHERE c.para_mantenimiento = 1
          AND c.id_activo_fijo_consumidor = :id_activo_fijo
          AND c.id_mantenimiento IS NULL
        ';

        return DB::select($sql, ['id_activo_fijo' => $id_activo_fijo]);
    }

    /**
     * Obtener consumos asociados a una lista de mantenimientos.
     */
    public static function get_consumos_por_mantenimientos(array $ids_mantenimiento): array
    {
        if (empty($ids_mantenimiento)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids_mantenimiento), '?'));
        $sql = "
        SELECT 
            c.id AS id_consumo,
            c.id_mantenimiento,
            c.cantidad_base_consumida AS cantidad,
            c.fecha_hora_consumo,
            c.comentario_consumo AS comentario,
            p.nombre AS producto,
            uni.abreviatura AS unidad
        FROM requerimiento_almacen_entrega_detalle_consumo c
        INNER JOIN requerimiento_almacen_entrega_detalle raed ON raed.id = c.id_requerimiento_almacen_entrega_detalle
        LEFT JOIN lote_producto lp ON lp.id = raed.id_lote_producto
        LEFT JOIN activo_fijo af ON af.id = raed.id_activo_fijo
        INNER JOIN producto p ON p.id = COALESCE(lp.id_producto, af.id_producto)
        INNER JOIN unidad_medida uni ON uni.id = p.id_unidad_medida_base
        WHERE c.id_mantenimiento IN ($placeholders)
        ";

        return DB::select($sql, $ids_mantenimiento);
    }

    /**
     * Registrar cabecera de mantenimiento.
     *
     * @param array $data
     */
    public static function crear_mantenimiento(array $data): int
    {
        return MantenimientoActivo::insertGetId($data);
    }
}
