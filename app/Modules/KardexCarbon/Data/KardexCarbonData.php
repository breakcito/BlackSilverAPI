<?php

namespace App\Modules\KardexCarbon\Data;

use Illuminate\Support\Facades\DB;

class KardexCarbonData
{
    /**
     * Lista los movimientos de Kardex de Carbón con datos del almacén, tipo de carbón y referencia de carga.
     * @param array{id_almacen?: int, id_tipo_carbon?: int, mes?: int, anio?: int, filtros?: string} $opts
     * @return array<object>
     */
    public static function get_movimientos(array $opts = []): array
    {
        $sql = '
            SELECT
                k.id AS id_kardex_carbon,
                k.id_almacen,
                alm.nombre AS almacen_nombre,
                k.id_tipo_carbon,
                tc.nombre AS tipo_carbon_nombre,
                tc.codigo AS tipo_carbon_codigo,
                k.id_carga_compra_carbon,
                c.codigo_ticket_balanza,
                c.placa,
                c.guia_remitente,
                cc.id AS id_compra_carbon,
                cc.correlativo AS compra_correlativo,
                prov.razon_social AS proveedor_razon_social,
                k.fecha_hora_movimiento,
                k.tipo_movimiento,
                k.stock_anterior,
                k.cantidad_movimiento,
                k.stock_resultante,
                k.costo_total,
                k.created_at
            FROM kardex_carbon k
            INNER JOIN almacen alm ON alm.id = k.id_almacen
            INNER JOIN tipo_carbon tc ON tc.id = k.id_tipo_carbon
            LEFT JOIN carga_compra_carbon c ON c.id = k.id_carga_compra_carbon
            LEFT JOIN compra_carbon cc ON cc.id = c.id_compra_carbon
            LEFT JOIN proveedor prov ON prov.id = cc.id_proveedor
            WHERE 1 = 1
        ';

        $params = [];

        if (!empty($opts['id_almacen'])) {
            $sql .= ' AND k.id_almacen = :id_almacen';
            $params['id_almacen'] = (int) $opts['id_almacen'];
        }

        if (!empty($opts['id_tipo_carbon'])) {
            $sql .= ' AND k.id_tipo_carbon = :id_tipo_carbon';
            $params['id_tipo_carbon'] = (int) $opts['id_tipo_carbon'];
        }

        $mes = isset($opts['mes']) ? (int) $opts['mes'] : 0;
        $anio = isset($opts['anio']) ? (int) $opts['anio'] : 0;
        if ($mes > 0 && $anio > 0) {
            $sql .= ' AND MONTH(k.fecha_hora_movimiento) = :mes AND YEAR(k.fecha_hora_movimiento) = :anio';
            $params['mes'] = $mes;
            $params['anio'] = $anio;
        } elseif ($anio > 0) {
            $sql .= ' AND YEAR(k.fecha_hora_movimiento) = :anio';
            $params['anio'] = $anio;
        }

        $filtros = trim((string) ($opts['filtros'] ?? ''));
        if ($filtros !== '') {
            $sql .= ' AND (tc.nombre LIKE :q OR tc.codigo LIKE :q OR c.codigo_ticket_balanza LIKE :q OR c.placa LIKE :q OR cc.correlativo LIKE :q)';
            $params['q'] = '%' . $filtros . '%';
        }

        $sql .= ' ORDER BY k.fecha_hora_movimiento DESC, k.id DESC';

        return DB::select($sql, $params);
    }
}
