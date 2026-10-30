<?php

namespace App\Modules\KardexCarbon\Data;

use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use Illuminate\Support\Facades\DB;

class KardexCarbonData
{
    /**
     * Lista los movimientos de Kardex de Carbón con datos del almacén, tipo de carbón y referencia de carga.
     *
     * Cada movimiento tiene SIEMPRE una carga de origen: las entradas vienen
     * de una carga de compra y las salidas de una carga de venta. Los
     * movimientos sin carga (huerfanos) no se listan.
     *
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
                k.id_carga_venta_carbon,
                c.codigo_ticket_balanza,
                c.placa,
                c.guia_remitente,
                cc.id AS id_compra_carbon,
                cc.correlativo AS compra_correlativo,
                prov.razon_social AS proveedor_razon_social,
                cv.codigo_ticket_balanza AS venta_codigo_ticket_balanza,
                veh.placa AS venta_placa,
                v.id AS id_venta_carbon,
                v.correlativo AS venta_correlativo,
                cli.razon_social AS cliente_razon_social,
                lv.codigo_orden_compra AS venta_codigo_orden_compra,
                lv.numero_reporte AS venta_numero_reporte,
                k.fecha_hora_movimiento,
                k.tipo_movimiento,
                k.calidad,
                k.stock_anterior,
                k.cantidad_movimiento,
                k.stock_resultante,
                k.costo_total,
                CASE
                    WHEN k.tipo_movimiento = :mov_ingreso THEN COALESCE(c.total, k.costo_total)
                    ELSE COALESCE(cv.total, k.costo_total)
                END AS costo_total_efectivo,
                k.created_at
            FROM kardex_carbon k
            INNER JOIN almacen alm ON alm.id = k.id_almacen
            INNER JOIN tipo_carbon tc ON tc.id = k.id_tipo_carbon
            LEFT JOIN carga_compra_carbon c ON c.id = k.id_carga_compra_carbon
            LEFT JOIN compra_carbon cc ON cc.id = c.id_compra_carbon
            LEFT JOIN proveedor prov ON prov.id = cc.id_proveedor
            LEFT JOIN carga_venta_carbon cv ON cv.id = k.id_carga_venta_carbon
            LEFT JOIN venta_carbon v ON v.id = cv.id_venta_carbon
            LEFT JOIN cliente cli ON cli.id = v.id_cliente
            LEFT JOIN liquidacion_venta_carbon lv ON lv.id = cv.id_liquidacion_venta_carbon
            LEFT JOIN vehiculo_carbon veh ON veh.id = cv.id_vehiculo_carbon
            WHERE 1 = 1
                AND (k.id_carga_compra_carbon IS NOT NULL OR k.id_carga_venta_carbon IS NOT NULL)
        ';

        $params = [
            'mov_ingreso' => KardexTipoMovimiento::Ingreso->value,
        ];

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
            $sql .= ' AND (tc.nombre LIKE :q OR tc.codigo LIKE :q OR c.codigo_ticket_balanza LIKE :q OR c.placa LIKE :q OR cc.correlativo LIKE :q OR cv.codigo_ticket_balanza LIKE :q OR veh.placa LIKE :q OR lv.codigo_orden_compra LIKE :q OR lv.numero_reporte LIKE :q OR cli.razon_social LIKE :q)';
            $params['q'] = '%' . $filtros . '%';
        }

        $sql .= ' ORDER BY k.fecha_hora_movimiento DESC, k.id DESC';

        return DB::select($sql, $params);
    }
}
