<?php

namespace App\Modules\Dashboard\Data;

use Illuminate\Support\Facades\DB;

/**
 * Indicador 1: stock critico por almacen.
 *
 * El universo es el INVENTARIO REAL: los pares (almacen, producto) que tienen
 * al menos un lote registrado en ese almacen. El stock minimo vive en
 * `producto.stock_minimo_base` y es GLOBAL (no existe un minimo configurable
 * por almacen), asi que el mismo umbral se aplica a todos los almacenes donde
 * el producto existe.
 *
 * El stock actual suma unicamente los lotes activos y NO vencidos: un lote
 * vencido no se puede consumir, asi que no cuenta como cobertura.
 */
class StockCriticoData
{
    /**
     * Productos por debajo de su stock minimo, ordenados por faltante.
     *
     * @param int|null $id_almacen Limita el resultado a un almacen.
     */
    public static function get_stock_critico(?int $id_almacen = null): array
    {
        $sql = '
        SELECT
            t.id_almacen,
            t.almacen,
            t.id_producto,
            t.producto,
            t.categoria,
            t.unidad,
            t.stock_minimo_base,
            t.es_auditable,
            t.stock_actual_base
        FROM (
            SELECT
                a.id AS id_almacen,
                a.nombre AS almacen,
                p.id AS id_producto,
                p.nombre AS producto,
                c.nombre AS categoria,
                um.abreviatura AS unidad,
                p.stock_minimo_base,
                p.es_auditable,
                COALESCE(SUM(
                    CASE
                        WHEN lp.estado = "Activo"
                         AND (lp.fecha_vencimiento IS NULL OR lp.fecha_vencimiento >= CURRENT_DATE)
                        THEN lp.stock_actual_base
                        ELSE 0
                    END
                ), 0) AS stock_actual_base
            FROM lote_producto lp
            INNER JOIN almacen a ON a.id = lp.id_almacen
            INNER JOIN producto p ON p.id = lp.id_producto
            LEFT JOIN categoria c ON c.id = p.id_categoria
            LEFT JOIN unidad_medida um ON um.id = p.id_unidad_medida_base
            WHERE lp.estado = "Activo"
              AND a.estado = "Activo"
              AND p.estado = "Activo"
              AND p.stock_minimo_base IS NOT NULL
              AND p.stock_minimo_base > 0
        ';

        $params = [];

        if ($id_almacen !== null) {
            $sql .= ' AND lp.id_almacen = :id_almacen';
            $params['id_almacen'] = $id_almacen;
        }

        // La agregacion va en una tabla derivada: MySQL con
        // only_full_group_by no admite ordenar por una expresion que mezcla
        // el alias agregado con una columna suelta.
        $sql .= '
            GROUP BY a.id, a.nombre, p.id, p.nombre, c.nombre, um.abreviatura, p.stock_minimo_base, p.es_auditable
        ) t
        WHERE t.stock_actual_base < t.stock_minimo_base
        ORDER BY (t.stock_minimo_base - t.stock_actual_base) DESC, t.almacen ASC, t.producto ASC
        ';

        $rows = DB::select($sql, $params);

        return array_map([self::class, 'normalizar'], $rows);
    }

    /**
     * Normaliza una fila cruda al contrato que consume el front.
     */
    private static function normalizar(object $row): array
    {
        $stock_actual = (float) $row->stock_actual_base;
        $stock_minimo = (float) $row->stock_minimo_base;

        return [
            'id_almacen' => (int) $row->id_almacen,
            'almacen' => (string) $row->almacen,
            'id_producto' => (int) $row->id_producto,
            'producto' => (string) $row->producto,
            'categoria' => $row->categoria !== null ? (string) $row->categoria : null,
            'unidad' => $row->unidad !== null ? (string) $row->unidad : null,
            'stock_actual_base' => round($stock_actual, 2),
            'stock_minimo_base' => round($stock_minimo, 2),
            'faltante_base' => round(max(0, $stock_minimo - $stock_actual), 2),
            // Cobertura: cuanto del minimo esta cubierto (1 = todo cubierto).
            'cobertura' => $stock_minimo > 0 ? round($stock_actual / $stock_minimo, 4) : 0,
            'estado' => $stock_actual <= 0 ? 'Sin stock' : 'Crítico',
            // El Modo Auditoria es del cliente: el backend no lo conoce y lo
            // resuelve el front filtrando los productos auditables.
            'es_auditable' => (bool) $row->es_auditable,
        ];
    }
}
