<?php

namespace App\Data;

use Illuminate\Support\Facades\DB;

/**
 * Catalogo global de stock de carbon.
 *
 * El stock se lleva por (almacen, tipo de carbon, calidad). Este catalogo
 * alimenta el autocompletado de la cantidad estimada al despachar una venta.
 */
class StockCarbonData
{
    /**
     * Lista el stock de carbon con filtros.
     * @param array{id_almacen?: int, id_tipo_carbon?: int, calidad?: string, filtros?: string} $opts
     * @return array<object>
     */
    public static function get_stocks(array $opts = []): array
    {
        $sql = '
            SELECT
                s.id AS id_stock_carbon,
                s.id_almacen,
                alm.nombre AS almacen_nombre,
                s.id_tipo_carbon,
                tc.nombre AS tipo_carbon_nombre,
                tc.codigo AS tipo_carbon_codigo,
                s.calidad,
                s.stock_actual
            FROM stock_carbon s
            INNER JOIN almacen alm ON alm.id = s.id_almacen
            INNER JOIN tipo_carbon tc ON tc.id = s.id_tipo_carbon
            WHERE 1 = 1
        ';

        $params = [];

        if (!empty($opts['id_almacen'])) {
            $sql .= ' AND s.id_almacen = :id_almacen';
            $params['id_almacen'] = (int) $opts['id_almacen'];
        }

        if (!empty($opts['id_tipo_carbon'])) {
            $sql .= ' AND s.id_tipo_carbon = :id_tipo_carbon';
            $params['id_tipo_carbon'] = (int) $opts['id_tipo_carbon'];
        }

        if (!empty($opts['calidad'])) {
            $sql .= ' AND s.calidad = :calidad';
            $params['calidad'] = (string) $opts['calidad'];
        }

        $filtros = trim((string) ($opts['filtros'] ?? ''));
        if ($filtros !== '') {
            $sql .= ' AND (tc.nombre LIKE :q OR tc.codigo LIKE :q OR alm.nombre LIKE :q)';
            $params['q'] = '%' . $filtros . '%';
        }

        $sql .= ' ORDER BY tc.nombre ASC, alm.nombre ASC, s.calidad ASC';

        return DB::select($sql, $params);
    }
}
