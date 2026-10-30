<?php

namespace App\Modules\CompraCarbon\Data;

use App\Models\CargaCompraCarbon;
use App\Models\CompraCarbon;
use App\Models\KardexCarbon;
use App\Models\StockCarbon;
use App\Modules\TipoCarbon\Data\CalidadCarbonData;
use App\Shared\Enums\CompraCarbon\EstadoCargaCompraCarbon;
use App\Shared\Enums\CompraCarbon\EstadoCompraCarbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CompraCarbonData
{
    /**
     * Lista compras de carbón con datos de proveedor, empresa, tipo prometido y totales.
     * @param array{filtros?: string, id_empresa?: int, id_proveedor?: int, mes?: int, anio?: int} $opts
     * @return array<object>
     */
    public static function get_compras(array $opts = []): array
    {
        $sql = '
            SELECT
                cc.id AS id_compra_carbon,
                cc.id_empresa,
                e.razon_social AS empresa,
                e.ruc AS empresa_ruc,
                cc.id_proveedor,
                p.razon_social AS proveedor,
                p.tipo_entidad AS proveedor_tipo_entidad,
                p.ruc AS proveedor_ruc,
                p.dni AS proveedor_dni,
                p.direccion AS proveedor_direccion,
                cc.id_tipo_carbon_prometido,
                tc_prom.nombre AS tipo_carbon_prometido,
                tc_prom.codigo AS tipo_carbon_prometido_codigo,
                tc_prom.ficha_tecnica,
                cc.id_tarifa_carbon,
                cc.id_cuenta_proveedor,
                cbp.moneda AS proveedor_cuenta_moneda,
                cbp.numero_cuenta AS proveedor_cuenta_numero,
                cbp.cci AS proveedor_cuenta_cci,
                bcp.nombre AS proveedor_cuenta_banco,
                bcp.abreviatura AS proveedor_cuenta_banco_abv,
                cc.correlativo,
                cc.numero_correlativo,
                cc.aplica_igv,
                cc.porcentaje_igv,
                cc.toneladas_prometidas,
                cc.precio_unitario_cotizado,
                cc.subtotal_cotizado,
                cc.total_cotizado,
                cc.monto_igv_cotizado,
                cc.porcentaje_detraccion,
                cc.monto_detraccion_cotizado,
                cc.neto_a_pagar_cotizado,
                cc.porcentaje_igv_flete,
                cc.porcentaje_detraccion_flete,
                cc.id_empleado_registro,
                CONCAT(er.nombre, " ", er.apellido) AS empleado_registro,
                cc.id_empleado_cierre,
                CONCAT(ec.nombre, " ", ec.apellido) AS empleado_cierre,
                cc.id_empleado_anula,
                CONCAT(ea.nombre, " ", ea.apellido) AS empleado_anula,
                cc.fecha_hora_cierre,
                cc.fecha_hora_anulacion,
                cc.log_cambios,
                cc.created_at,
                cc.estado,
                (
                    SELECT COUNT(*)
                    FROM carga_compra_carbon c
                    WHERE c.id_compra_carbon = cc.id
                ) AS cantidad_cargas,
                (
                    SELECT COUNT(*)
                    FROM carga_compra_carbon c
                    WHERE c.id_compra_carbon = cc.id
                      AND c.pagar_flete = 1
                      AND c.estado <> "Anulado"
                ) AS cantidad_cargas_con_flete,
                (
                    SELECT COALESCE(SUM(c.cantidad), 0)
                    FROM carga_compra_carbon c
                    WHERE c.id_compra_carbon = cc.id AND c.estado <> "Anulado"
                ) AS total_toneladas_reales,
                (
                    SELECT COALESCE(SUM(c.subtotal), 0)
                    FROM carga_compra_carbon c
                    WHERE c.id_compra_carbon = cc.id AND c.estado <> "Anulado"
                ) AS total_real_subtotal,
                (
                    SELECT COALESCE(SUM(c.neto_a_pagar), 0)
                    FROM carga_compra_carbon c
                    WHERE c.id_compra_carbon = cc.id AND c.estado <> "Anulado"
                ) AS total_real_neto,
                (
                    SELECT COALESCE(SUM(c.flete_neto_a_pagar), 0)
                    FROM carga_compra_carbon c
                    WHERE c.id_compra_carbon = cc.id AND c.estado <> "Anulado"
                ) AS total_real_flete_neto
            FROM compra_carbon cc
            INNER JOIN empresa e ON e.id = cc.id_empresa
            INNER JOIN proveedor p ON p.id = cc.id_proveedor
            INNER JOIN empleado er ON er.id = cc.id_empleado_registro
            LEFT JOIN tipo_carbon tc_prom ON tc_prom.id = cc.id_tipo_carbon_prometido
            LEFT JOIN cuenta_bancaria_proveedor cbp ON cbp.id = cc.id_cuenta_proveedor
            LEFT JOIN banco bcp ON bcp.id = cbp.id_banco
            LEFT JOIN empleado ec ON ec.id = cc.id_empleado_cierre
            LEFT JOIN empleado ea ON ea.id = cc.id_empleado_anula
            WHERE 1 = 1
        ';

        $params = [];

        if (!empty($opts['id_compra_carbon'])) {
            $sql .= ' AND cc.id = :id_compra_carbon';
            $params['id_compra_carbon'] = (int) $opts['id_compra_carbon'];
        }
        if (!empty($opts['id_empresa'])) {
            $sql .= ' AND cc.id_empresa = :id_empresa';
            $params['id_empresa'] = (int) $opts['id_empresa'];
        }
        if (!empty($opts['id_proveedor'])) {
            $sql .= ' AND cc.id_proveedor = :id_proveedor';
            $params['id_proveedor'] = (int) $opts['id_proveedor'];
        }

        $filtros = trim((string) ($opts['filtros'] ?? ''));
        if ($filtros !== '') {
            $sql .= ' AND (cc.correlativo LIKE :q OR p.razon_social LIKE :q OR e.razon_social LIKE :q)';
            $params['q'] = '%' . $filtros . '%';
        }

        $mes = isset($opts['mes']) ? (int) $opts['mes'] : 0;
        $anio = isset($opts['anio']) ? (int) $opts['anio'] : 0;
        if ($mes > 0 && $anio > 0) {
            $sql .= ' AND MONTH(cc.created_at) = :mes AND YEAR(cc.created_at) = :anio';
            $params['mes'] = $mes;
            $params['anio'] = $anio;
        } elseif ($anio > 0) {
            $sql .= ' AND YEAR(cc.created_at) = :anio';
            $params['anio'] = $anio;
        }

        $sql .= ' ORDER BY cc.id DESC';

        $rows = DB::select($sql, $params);
        foreach ($rows as $row) {
            $row->log_cambios = self::decode_json($row->log_cambios ?? null);
            $row->ficha_tecnica = self::decode_json($row->ficha_tecnica ?? null);
        }

        return $rows;
    }

    /**
     * Obtiene una compra por su id con la misma estructura del listado.
     */
    public static function get_compra_by_id(int $id_compra_carbon): ?object
    {
        $rows = self::get_compras(['id_compra_carbon' => $id_compra_carbon]);
        return $rows[0] ?? null;
    }

    /**
     * Trae cabecera + cargas + comprobantes + pagos + anticipos de una compra por id.
     * @return array<string, mixed>
     */
    public static function get_compra_con_detalles(int $id_compra_carbon): array
    {
        $sqlCabecera = '
            SELECT
                cc.id AS id_compra_carbon,
                cc.id_empresa,
                e.razon_social AS empresa,
                e.ruc AS empresa_ruc,
                cc.id_proveedor,
                p.razon_social AS proveedor,
                p.tipo_entidad AS proveedor_tipo_entidad,
                p.ruc AS proveedor_ruc,
                p.dni AS proveedor_dni,
                p.direccion AS proveedor_direccion,
                cc.id_tipo_carbon_prometido,
                tc_prom.nombre AS tipo_carbon_prometido,
                tc_prom.codigo AS tipo_carbon_prometido_codigo,
                tc_prom.ficha_tecnica,
                cc.id_tarifa_carbon,
                cc.id_cuenta_proveedor,
                cbp.moneda AS proveedor_cuenta_moneda,
                cbp.numero_cuenta AS proveedor_cuenta_numero,
                cbp.cci AS proveedor_cuenta_cci,
                bcp.nombre AS proveedor_cuenta_banco,
                bcp.abreviatura AS proveedor_cuenta_banco_abv,
                cc.correlativo,
                cc.numero_correlativo,
                cc.aplica_igv,
                cc.porcentaje_igv,
                cc.toneladas_prometidas,
                cc.precio_unitario_cotizado,
                cc.subtotal_cotizado,
                cc.total_cotizado,
                cc.monto_igv_cotizado,
                cc.porcentaje_detraccion,
                cc.monto_detraccion_cotizado,
                cc.neto_a_pagar_cotizado,
                cc.porcentaje_igv_flete,
                cc.porcentaje_detraccion_flete,
                cc.id_empleado_registro,
                CONCAT(er.nombre, " ", er.apellido) AS empleado_registro,
                cc.id_empleado_cierre,
                CONCAT(ec.nombre, " ", ec.apellido) AS empleado_cierre,
                cc.id_empleado_anula,
                CONCAT(ea.nombre, " ", ea.apellido) AS empleado_anula,
                cc.fecha_hora_cierre,
                cc.fecha_hora_anulacion,
                cc.log_cambios,
                cc.created_at,
                cc.estado,
                (
                    SELECT COUNT(*)
                    FROM carga_compra_carbon c
                    WHERE c.id_compra_carbon = cc.id AND c.estado <> "Anulado"
                ) AS cantidad_cargas,
                (
                    SELECT COUNT(*)
                    FROM carga_compra_carbon c
                    WHERE c.id_compra_carbon = cc.id
                      AND c.pagar_flete = 1
                      AND c.estado <> "Anulado"
                ) AS cantidad_cargas_con_flete,
                (
                    SELECT COALESCE(SUM(c.cantidad), 0)
                    FROM carga_compra_carbon c
                    WHERE c.id_compra_carbon = cc.id AND c.estado <> "Anulado"
                ) AS total_toneladas_reales,
                (
                    SELECT COALESCE(SUM(c.subtotal), 0)
                    FROM carga_compra_carbon c
                    WHERE c.id_compra_carbon = cc.id AND c.estado <> "Anulado"
                ) AS total_real_subtotal,
                (
                    SELECT COALESCE(SUM(c.neto_a_pagar), 0)
                    FROM carga_compra_carbon c
                    WHERE c.id_compra_carbon = cc.id AND c.estado <> "Anulado"
                ) AS total_real_neto,
                (
                    SELECT COALESCE(SUM(c.flete_neto_a_pagar), 0)
                    FROM carga_compra_carbon c
                    WHERE c.id_compra_carbon = cc.id AND c.estado <> "Anulado"
                ) AS total_real_flete_neto
            FROM compra_carbon cc
            INNER JOIN empresa e ON e.id = cc.id_empresa
            INNER JOIN proveedor p ON p.id = cc.id_proveedor
            INNER JOIN empleado er ON er.id = cc.id_empleado_registro
            LEFT JOIN tipo_carbon tc_prom ON tc_prom.id = cc.id_tipo_carbon_prometido
            LEFT JOIN cuenta_bancaria_proveedor cbp ON cbp.id = cc.id_cuenta_proveedor
            LEFT JOIN banco bcp ON bcp.id = cbp.id_banco
            LEFT JOIN empleado ec ON ec.id = cc.id_empleado_cierre
            LEFT JOIN empleado ea ON ea.id = cc.id_empleado_anula
            WHERE cc.id = :id
            LIMIT 1
        ';

        $cabecera = DB::selectOne($sqlCabecera, ['id' => $id_compra_carbon]);
        if ($cabecera !== null) {
            $cabecera->log_cambios = self::decode_json($cabecera->log_cambios ?? null);
            $cabecera->ficha_tecnica = self::decode_json($cabecera->ficha_tecnica ?? null);
        }

        $hasColCompTrans = Schema::hasColumn('carga_compra_carbon', 'id_comprobante_transporte_carbon');
        $colCompTrans = $hasColCompTrans
            ? "c.id_comprobante_transporte_carbon,\n                com_tr.codigo_comprobante AS comprobante_transporte_codigo,\n                com_tr.fecha_emision AS comprobante_transporte_fecha_emision,"
            : "NULL AS id_comprobante_transporte_carbon,\n                NULL AS comprobante_transporte_codigo,\n                NULL AS comprobante_transporte_fecha_emision,";
        $joinCompTrans = $hasColCompTrans
            ? "LEFT JOIN comprobante_transporte_carbon com_tr ON com_tr.id = c.id_comprobante_transporte_carbon"
            : "";

        $sqlCargas = '
            SELECT
                c.id AS id_carga_compra_carbon,
                c.id_compra_carbon,
                c.id_empleado_registro,
                CONCAT(e.nombre, " ", e.apellido) AS empleado_registro,
                c.id_tipo_carbon,
                tc.nombre AS tipo_carbon_nombre,
                tc.codigo AS tipo_carbon_codigo,
                c.id_lugar_extraccion,
                le.direccion AS lugar_extraccion_nombre,
                le.direccion AS lugar_extraccion_direccion,
                c.id_almacen_proveedor_recojo,
                aprov.direccion AS almacen_proveedor_direccion,
                c.id_almacen_empresa_llegada,
                alm.nombre AS almacen_empresa_nombre,
                c.id_almacen_cliente_llegada,
                ac.direccion AS almacen_cliente_direccion,
                cli.razon_social AS cliente_destino,
                c.id_tarifa_carbon,
                tar.inicio_porcentaje_ceniza AS tarifa_inicio_ceniza,
                tar.fin_porcentaje_ceniza AS tarifa_fin_ceniza,
                tar.precio_unitario AS tarifa_precio_unitario,
                c.id_transportista,
                tr.razon_social AS transportista_razon_social,
                tr.ruc AS transportista_ruc,
                tr.dni AS transportista_dni,
                ' . $colCompTrans . '
                c.id_comprobante_compra_carbon,
                com_pr.codigo_comprobante AS comprobante_compra_codigo,
                c.id_pago_compra_carbon,
                p_dir.numero_operacion AS pago_directo_numero_operacion,
                c.tipo_despacho,
                c.placa,
                c.fecha_hora_ingreso,
                c.guia_remitente,
                c.guia_transportista,
                c.pagar_flete,
                c.codigo_ticket_balanza,
                c.cantidad,
                c.porcentaje_ceniza,
                c.calidad,
                c.porcentaje_humedad,
                c.precio_unitario,
                c.costo_flete_por_tonelada,
                c.precio_materia_prima,
                c.subtotal,
                c.monto_igv,
                c.total,
                c.monto_detraccion,
                c.neto_a_pagar,
                c.flete_subtotal,
                c.flete_igv,
                c.flete_total,
                c.flete_monto_detraccion,
                c.flete_neto_a_pagar,
                c.evidencias,
                c.log_cambios,
                c.created_at,
                c.estado
            FROM carga_compra_carbon c
            INNER JOIN empleado e ON e.id = c.id_empleado_registro
            INNER JOIN tipo_carbon tc ON tc.id = c.id_tipo_carbon
            LEFT JOIN lugar_extraccion_carbon le ON le.id = c.id_lugar_extraccion
            LEFT JOIN almacen_carbon_proveedor aprov ON aprov.id = c.id_almacen_proveedor_recojo
            LEFT JOIN almacen alm ON alm.id = c.id_almacen_empresa_llegada
            LEFT JOIN almacen_carbon_cliente ac ON ac.id = c.id_almacen_cliente_llegada
            LEFT JOIN cliente cli ON cli.id = ac.id_cliente
            LEFT JOIN tarifa_carbon tar ON tar.id = c.id_tarifa_carbon
            LEFT JOIN transportista tr ON tr.id = c.id_transportista
            ' . $joinCompTrans . '
            LEFT JOIN comprobante_compra_carbon com_pr ON com_pr.id = c.id_comprobante_compra_carbon
            LEFT JOIN pago_compra_carbon p_dir ON p_dir.id = c.id_pago_compra_carbon
            WHERE c.id_compra_carbon = :id
            ORDER BY c.id ASC
        ';

        $cargas = DB::select($sqlCargas, ['id' => $id_compra_carbon]);
        foreach ($cargas as $c) {
            $c->evidencias = self::decode_json($c->evidencias ?? null);
            $c->log_cambios = self::decode_json($c->log_cambios ?? null);
        }

        // Comprobantes del proveedor
        $sqlComprobantes = '
            SELECT
                cmp.id AS id_comprobante_compra_carbon,
                cmp.id_empleado_registro,
                CONCAT(e.nombre, " ", e.apellido) AS empleado_registro,
                cmp.id_compra_carbon,
                cmp.codigo_comprobante,
                cmp.fecha_emision,
                cmp.observacion,
                cmp.evidencias,
                cmp.subtotal,
                cmp.monto_pagado_anticipos,
                cmp.base_imponible,
                cmp.monto_igv,
                cmp.importe_total,
                cmp.monto_detraccion,
                cmp.monto_neto,
                cmp.avance_pago_detraccion,
                cmp.avance_pago_neto,
                cmp.created_at,
                cmp.estado
            FROM comprobante_compra_carbon cmp
            INNER JOIN empleado e ON e.id = cmp.id_empleado_registro
            WHERE cmp.id_compra_carbon = :id
            ORDER BY cmp.id ASC
        ';
        $comprobantesProveedor = DB::select($sqlComprobantes, ['id' => $id_compra_carbon]);
        foreach ($comprobantesProveedor as $cmp) {
            $cmp->evidencias = self::decode_json($cmp->evidencias ?? null);
            // Pagos asociados a este comprobante
            $cmp->pagos = DB::select('
                SELECT
                    p.id AS id_pago_compra_carbon,
                    p.id_compra_carbon,
                    p.id_comprobante_compra_carbon,
                    p.id_cuenta_bancaria_empresa,
                    bce.nombre AS empresa_banco,
                    cbe.numero_cuenta AS empresa_numero_cuenta,
                    p.id_cuenta_bancaria_proveedor,
                    bcp.nombre AS proveedor_banco,
                    cbp.numero_cuenta AS proveedor_numero_cuenta,
                    p.id_empleado_registro,
                    CONCAT(ep.nombre, " ", ep.apellido) AS empleado_registro,
                    p.medio_pago,
                    p.numero_operacion,
                    p.fecha_hora_pago,
                    p.es_para_detraccion,
                    p.observacion,
                    p.evidencias,
                    p.monto_pagado,
                    p.created_at
                FROM pago_compra_carbon p
                LEFT JOIN cuenta_bancaria_empresa cbe ON cbe.id = p.id_cuenta_bancaria_empresa
                LEFT JOIN banco bce on bce.id = cbe.id_banco
                LEFT JOIN cuenta_bancaria_proveedor cbp ON cbp.id = p.id_cuenta_bancaria_proveedor
                LEFT JOIN banco bcp on bcp.id = cbp.id_banco
                INNER JOIN empleado ep ON ep.id = p.id_empleado_registro
                WHERE p.id_comprobante_compra_carbon = :id_cmp
                ORDER BY p.id ASC
            ', ['id_cmp' => $cmp->id_comprobante_compra_carbon]);
            foreach ($cmp->pagos as $p) {
                $p->evidencias = self::decode_json($p->evidencias ?? null);
            }

            // Anticipos aplicados a este comprobante
            $cmp->anticipos_aplicados = DB::select('
                SELECT
                    tap.id AS id_transaccion,
                    tap.id_anticipo_proveedor,
                    tap.monto_retirado,
                    ap.medio_pago,
                    ap.numero_operacion,
                    ap.fecha_hora_pago,
                    ap.codigo_comprobante
                FROM transaccion_anticipo_proveedor tap
                INNER JOIN anticipo_proveedor ap ON ap.id = tap.id_anticipo_proveedor
                WHERE tap.id_comprobante_compra_carbon = :id_cmp
            ', ['id_cmp' => $cmp->id_comprobante_compra_carbon]);
        }

        // Pagos directos (sin comprobante) de la compra
        $sqlPagosDirectos = '
            SELECT
                p.id AS id_pago_compra_carbon,
                p.id_compra_carbon,
                p.id_cuenta_bancaria_empresa,
                bce.nombre AS empresa_banco,
                cbe.numero_cuenta AS empresa_numero_cuenta,
                p.id_cuenta_bancaria_proveedor,
                bcp.nombre AS proveedor_banco,
                cbp.numero_cuenta AS proveedor_numero_cuenta,
                p.id_empleado_registro,
                CONCAT(ep.nombre, " ", ep.apellido) AS empleado_registro,
                p.medio_pago,
                p.numero_operacion,
                p.fecha_hora_pago,
                p.es_para_detraccion,
                p.observacion,
                p.evidencias,
                p.monto_pagado,
                p.created_at
            FROM pago_compra_carbon p
            LEFT JOIN cuenta_bancaria_empresa cbe ON cbe.id = p.id_cuenta_bancaria_empresa
            LEFT JOIN banco bce ON bce.id = cbe.id_banco
            LEFT JOIN cuenta_bancaria_proveedor cbp ON cbp.id = p.id_cuenta_bancaria_proveedor
            LEFT JOIN banco bcp ON bcp.id = cbp.id_banco
            INNER JOIN empleado ep ON ep.id = p.id_empleado_registro
            WHERE p.id_compra_carbon = :id AND p.id_comprobante_compra_carbon IS NULL
            ORDER BY p.id ASC
        ';
        $pagosDirectos = DB::select($sqlPagosDirectos, ['id' => $id_compra_carbon]);
        foreach ($pagosDirectos as $p) {
            $p->evidencias = self::decode_json($p->evidencias ?? null);
            // Anticipos aplicados a este pago
            $p->anticipos_aplicados = DB::select('
                SELECT
                    tap.id AS id_transaccion,
                    tap.id_anticipo_proveedor,
                    tap.monto_retirado,
                    ap.medio_pago,
                    ap.numero_operacion,
                    ap.fecha_hora_pago,
                    ap.codigo_comprobante
                FROM transaccion_anticipo_proveedor tap
                INNER JOIN anticipo_proveedor ap ON ap.id = tap.id_anticipo_proveedor
                WHERE tap.id_pago_compra_carbon = :id_pago
            ', ['id_pago' => $p->id_pago_compra_carbon]);
        }

        // Comprobantes de flete / transporte
        $sqlComprobantesTransporte = '
            SELECT
                ct.id AS id_comprobante_transporte_carbon,
                ct.id_compra_carbon,
                ct.id_empleado_registro,
                CONCAT(e.nombre, " ", e.apellido) AS empleado_registro,
                ct.id_transportista,
                tr.razon_social AS transportista_razon_social,
                ct.codigo_comprobante,
                ct.fecha_emision,
                ct.observacion,
                ct.evidencias,
                ct.subtotal,
                ct.monto_igv,
                ct.importe_total,
                ct.monto_detraccion,
                ct.monto_neto,
                ct.avance_pago_detraccion,
                ct.avance_pago_neto,
                ct.created_at,
                ct.estado
            FROM comprobante_transporte_carbon ct
            INNER JOIN empleado e ON e.id = ct.id_empleado_registro
            INNER JOIN transportista tr ON tr.id = ct.id_transportista
            WHERE ct.id_compra_carbon = :id
            ORDER BY ct.id ASC
        ';
        $comprobantesTransporte = DB::select($sqlComprobantesTransporte, ['id' => $id_compra_carbon]);
        foreach ($comprobantesTransporte as $ct) {
            $ct->evidencias = self::decode_json($ct->evidencias ?? null);
            $ct->pagos = DB::select('
                SELECT
                    pt.id AS id_pago_transporte_carbon,
                    pt.id_compra_carbon,
                    pt.id_comprobante_transporte_carbon,
                    pt.id_cuenta_bancaria_empresa,
                    bce.nombre AS empresa_banco,
                    cbe.numero_cuenta AS empresa_numero_cuenta,
                    pt.id_cuenta_bancaria_transportista,
                    bct.nombre AS transportista_banco,
                    cbt.numero_cuenta AS transportista_numero_cuenta,
                    pt.id_empleado_registro,
                    CONCAT(ep.nombre, " ", ep.apellido) AS empleado_registro,
                    pt.medio_pago,
                    pt.numero_operacion,
                    pt.fecha_hora_pago,
                    pt.es_para_detraccion,
                    pt.observacion,
                    pt.evidencias,
                    pt.monto_pagado,
                    pt.created_at
                FROM pago_transporte_carbon pt
                LEFT JOIN cuenta_bancaria_empresa cbe ON cbe.id = pt.id_cuenta_bancaria_empresa
                LEFT JOIN banco bce ON bce.id = cbe.id_banco
                LEFT JOIN cuenta_bancaria_transportista cbt ON cbt.id = pt.id_cuenta_bancaria_transportista
                LEFT JOIN banco bct ON bct.id = cbt.id_banco
                INNER JOIN empleado ep ON ep.id = pt.id_empleado_registro
                WHERE pt.id_comprobante_transporte_carbon = :id_ct
                ORDER BY pt.id ASC
            ', ['id_ct' => $ct->id_comprobante_transporte_carbon]);
            foreach ($ct->pagos as $p) {
                $p->evidencias = self::decode_json($p->evidencias ?? null);
            }
        }

        // Todos los anticipos usados en esta compra
        $sqlAnticiposConsolidados = '
            SELECT
                tap.id AS id_transaccion,
                tap.id_anticipo_proveedor,
                tap.id_comprobante_compra_carbon,
                tap.id_pago_compra_carbon,
                tap.monto_retirado,
                ap.medio_pago,
                ap.numero_operacion,
                ap.fecha_hora_pago,
                ap.codigo_comprobante,
                ap.observacion
            FROM transaccion_anticipo_proveedor tap
            INNER JOIN anticipo_proveedor ap ON ap.id = tap.id_anticipo_proveedor
            WHERE tap.id_compra_carbon = :id
            ORDER BY tap.id ASC
        ';
        $anticiposConsolidados = DB::select($sqlAnticiposConsolidados, ['id' => $id_compra_carbon]);

        // Transportistas con flete en esta compra, con la primera cuenta
        // bancaria en soles que NO es de detracción (la que recibe el flete).
        $sqlTransportistasFlete = '
            SELECT
                t.id AS id_transportista,
                t.razon_social,
                t.ruc,
                t.dni,
                cbt.numero_cuenta,
                cbt.cci,
                b.nombre AS banco
            FROM transportista t
            INNER JOIN (
                SELECT DISTINCT c.id_transportista
                FROM carga_compra_carbon c
                WHERE c.id_compra_carbon = :id
                  AND c.pagar_flete = 1
                  AND c.id_transportista IS NOT NULL
                  AND c.estado <> "Anulado"
            ) dt ON dt.id_transportista = t.id
            LEFT JOIN cuenta_bancaria_transportista cbt ON cbt.id = (
                SELECT c2.id
                FROM cuenta_bancaria_transportista c2
                WHERE c2.id_transportista = t.id
                  AND c2.es_para_detraccion = 0
                  AND c2.moneda = "Soles"
                ORDER BY c2.id ASC
                LIMIT 1
            )
            LEFT JOIN banco b ON b.id = cbt.id_banco
            ORDER BY t.razon_social ASC
        ';
        $transportistasFlete = DB::select($sqlTransportistasFlete, ['id' => $id_compra_carbon]);

        return [
            'cabecera' => $cabecera,
            'cargas' => $cargas,
            'comprobantes_proveedor' => $comprobantesProveedor,
            'pagos_directos' => $pagosDirectos,
            'comprobantes_transporte' => $comprobantesTransporte,
            'anticipos_utilizados' => $anticiposConsolidados,
            'transportistas_flete' => $transportistasFlete,
        ];
    }

    /**
     * Devuelve la tarifa activa con el precio más alto para un tipo de carbón.
     */
    public static function get_tarifa_max_precio(int $id_tipo_carbon): ?object
    {
        return DB::table('tarifa_carbon')
            ->where('id_tipo_carbon', $id_tipo_carbon)
            ->where(function ($q) {
                $q->whereNull('estado')->orWhere('estado', 'Activo');
            })
            ->orderByDesc('precio_unitario')
            ->first();
    }

    /**
     * Busca la tarifa adecuada según el porcentaje de ceniza.
     */
    public static function get_tarifa_por_ceniza(int $id_tipo_carbon, float $ceniza): ?object
    {
        return DB::table('tarifa_carbon')
            ->where('id_tipo_carbon', $id_tipo_carbon)
            ->where(function ($q) {
                $q->whereNull('estado')->orWhere('estado', 'Activo');
            })
            ->where('inicio_porcentaje_ceniza', '<=', $ceniza)
            ->where('fin_porcentaje_ceniza', '>=', $ceniza)
            ->orderByDesc('precio_unitario')
            ->first();
    }

    /**
     * Genera el siguiente correlativo anual de compra de carbón.
     * @return array{correlativo: string, numero_correlativo: int}
     */
    public static function generar_correlativo(int $anio): array
    {
        $max = DB::table('compra_carbon')
            ->whereRaw('YEAR(created_at) = ?', [$anio])
            ->max('numero_correlativo');

        $sig = ((int) $max) + 1;
        $correlativo = sprintf('%04d-%d', $sig, $anio);

        return [
            'correlativo' => $correlativo,
            'numero_correlativo' => $sig,
        ];
    }

    /**
     * Inserta la orden de compra preliminar.
     * @param array<string, mixed> $data
     */
    public static function insert_compra(array $data): int
    {
        $aplica_igv = !empty($data['aplica_igv']) ? 1 : 0;
        $payload = [
            'id_empresa' => (int) $data['id_empresa'],
            'id_proveedor' => (int) $data['id_proveedor'],
            'id_empleado_registro' => (int) $data['id_empleado_registro'],
            'id_tipo_carbon_prometido' => (int) $data['id_tipo_carbon_prometido'],
            'id_tarifa_carbon' => isset($data['id_tarifa_carbon']) && (int) $data['id_tarifa_carbon'] > 0
                ? (int) $data['id_tarifa_carbon']
                : null,
            'correlativo' => (string) $data['correlativo'],
            'numero_correlativo' => (int) $data['numero_correlativo'],
            'aplica_igv' => $aplica_igv,
            'porcentaje_igv' => (float) ($data['porcentaje_igv'] ?? 0),
            // La detracción del carbón solo aplica cuando la compra emite IGV.
            'porcentaje_detraccion' => (float) ($data['porcentaje_detraccion'] ?? ($aplica_igv == 1 ? 10.0 : 0.0)),
            // El flete es un servicio facturado aparte: su IGV y su detracción
            // se aplican siempre, con independencia de que la compra aplique IGV.
            'porcentaje_igv_flete' => (float) ($data['porcentaje_igv_flete'] ?? 18.0),
            'porcentaje_detraccion_flete' => (float) ($data['porcentaje_detraccion_flete'] ?? 4.0),
            'toneladas_prometidas' => (float) $data['toneladas_prometidas'],
            'precio_unitario_cotizado' => (float) $data['precio_unitario_cotizado'],
            'subtotal_cotizado' => (float) ($data['subtotal_cotizado'] ?? 0),
            'monto_igv_cotizado' => (float) ($data['monto_igv_cotizado'] ?? 0),
            'total_cotizado' => (float) ($data['total_cotizado'] ?? 0),
            'monto_detraccion_cotizado' => (float) ($data['monto_detraccion_cotizado'] ?? 0),
            'neto_a_pagar_cotizado' => (float) ($data['neto_a_pagar_cotizado'] ?? 0),
            'log_cambios' => null,
            'created_at' => (string) ($data['created_at'] ?? now()->toDateTimeString()),
            'estado' => EstadoCompraCarbon::Preliminar->value,
        ];

        if (Schema::hasColumn('compra_carbon', 'id_cuenta_proveedor')) {
            $payload['id_cuenta_proveedor'] = isset($data['id_cuenta_proveedor']) && (int) $data['id_cuenta_proveedor'] > 0
                ? (int) $data['id_cuenta_proveedor']
                : null;
        }

        return CompraCarbon::insertGetId($payload);
    }

    /**
     * Inserta una o varias cargas en carga_compra_carbon y afecta stock/kardex si ingresan a almacén de empresa.
     * @param array<int, array<string, mixed>> $cargas
     * @return array<int> IDs de las cargas insertadas
     */
    public static function insert_cargas(int $id_compra_carbon, array $cargas, int $id_empleado): array
    {
        return DB::transaction(function () use ($id_compra_carbon, $cargas, $id_empleado) {
            $idsInsertados = [];

            // Porcentajes de la compra: gobiernan el IGV y la detracción del
            // carbón y los del flete. Sin compra no hay carga.
            $compra = DB::table('compra_carbon')
                ->where('id', $id_compra_carbon)
                ->first();

            if ($compra === null) {
                throw new \RuntimeException(
                    'No se puede registrar una carga sin una orden de compra de carbón'
                );
            }

            $porcentajeIgv = (float) $compra->porcentaje_igv;
            $porcentajeDetraccion = (float) $compra->porcentaje_detraccion;
            $porcentajeIgvFlete = (float) $compra->porcentaje_igv_flete;
            $porcentajeDetraccionFlete = (float) $compra->porcentaje_detraccion_flete;

            foreach ($cargas as $c) {
                $idAlmacenEmpresa = isset($c['id_almacen_empresa_llegada']) && (int) $c['id_almacen_empresa_llegada'] > 0
                    ? (int) $c['id_almacen_empresa_llegada']
                    : null;
                $idAlmacenCliente = isset($c['id_almacen_cliente_llegada']) && (int) $c['id_almacen_cliente_llegada'] > 0
                    ? (int) $c['id_almacen_cliente_llegada']
                    : null;
                $idAlmacenProveedor = isset($c['id_almacen_proveedor_recojo']) && (int) $c['id_almacen_proveedor_recojo'] > 0
                    ? (int) $c['id_almacen_proveedor_recojo']
                    : null;

                $pagarFlete = !empty($c['pagar_flete']);
                $cantidad = (float) $c['cantidad'];
                $precioUnitario = (float) $c['precio_unitario'];
                $costoFlete = $pagarFlete ? (float) ($c['costo_flete_por_tonelada'] ?? 0) : 0.0;
                $porcentajeCeniza = (float) ($c['porcentaje_ceniza'] ?? 0);

                // La calidad se deriva del % de ceniza segun la tabla calidad_carbon.
                $calidad = CalidadCarbonData::resolver_por_ceniza($porcentajeCeniza);
                $nombreCalidad = $calidad !== null ? (string) $calidad->nombre : null;

                // ── COSTOS DEL CARBON ──────────────────────────────────────
                // El flete lo asume la empresa, asi que sale del precio unitario
                // que se le reconoce al proveedor por el carbon.
                $precioMateriaPrima = max(0.0, round($precioUnitario - $costoFlete, 6));
                $subtotal = round($precioMateriaPrima * $cantidad, 2);
                $montoIgv = $porcentajeIgv > 0 ? round($subtotal * ($porcentajeIgv / 100), 2) : 0.0;
                $total = round($subtotal + $montoIgv, 2);
                $montoDetraccion = $porcentajeDetraccion > 0 ? round($total * ($porcentajeDetraccion / 100), 2) : 0.0;
                $netoAPagar = round($total - $montoDetraccion, 2);

                // ── COSTOS DE TRANSPORTE ────────────────────────────────────
                $fleteSubtotal = $pagarFlete ? round($cantidad * $costoFlete, 2) : 0.0;
                $fleteIgv = $porcentajeIgvFlete > 0 ? round($fleteSubtotal * ($porcentajeIgvFlete / 100), 2) : 0.0;
                $fleteTotal = round($fleteSubtotal + $fleteIgv, 2);
                $fleteMontoDetraccion = $porcentajeDetraccionFlete > 0 ? round($fleteTotal * ($porcentajeDetraccionFlete / 100), 2) : 0.0;
                $fleteNetoAPagar = round($fleteTotal - $fleteMontoDetraccion, 2);

                $evidenciasJson = isset($c['evidencias']) && $c['evidencias'] !== null
                    ? (is_string($c['evidencias']) ? $c['evidencias'] : json_encode($c['evidencias'], JSON_UNESCAPED_UNICODE))
                    : null;

                $cargaDataToInsert = [
                    'id_compra_carbon' => $id_compra_carbon,
                    'id_empleado_registro' => $id_empleado,
                    'id_tipo_carbon' => (int) $c['id_tipo_carbon'],
                    'id_lugar_extraccion' => isset($c['id_lugar_extraccion']) && (int) $c['id_lugar_extraccion'] > 0 ? (int) $c['id_lugar_extraccion'] : null,
                    'id_almacen_proveedor_recojo' => $idAlmacenProveedor,
                    'id_almacen_empresa_llegada' => $idAlmacenEmpresa,
                    'id_almacen_cliente_llegada' => $idAlmacenCliente,
                    'id_tarifa_carbon' => isset($c['id_tarifa_carbon']) && (int) $c['id_tarifa_carbon'] > 0 ? (int) $c['id_tarifa_carbon'] : null,
                    'id_transportista' => $pagarFlete && isset($c['id_transportista']) && (int) $c['id_transportista'] > 0 ? (int) $c['id_transportista'] : null,
                    'id_comprobante_compra_carbon' => null,
                    'id_pago_compra_carbon' => null,
                    'tipo_despacho' => (string) ($c['tipo_despacho'] ?? 'Envio'),
                    'placa' => (string) ($c['placa'] ?? ''),
                    'fecha_hora_ingreso' => (string) ($c['fecha_hora_ingreso'] ?? now()->toDateTimeString()),
                    'guia_remitente' => (string) ($c['guia_remitente'] ?? ''),
                    'guia_transportista' => isset($c['guia_transportista']) && $c['guia_transportista'] !== '' ? (string) $c['guia_transportista'] : null,
                    'pagar_flete' => $pagarFlete ? 1 : 0,
                    'codigo_ticket_balanza' => (string) ($c['codigo_ticket_balanza'] ?? ''),
                    'cantidad' => $cantidad,
                    'porcentaje_ceniza' => $porcentajeCeniza,
                    'porcentaje_humedad' => (float) ($c['porcentaje_humedad'] ?? 0),
                    'precio_unitario' => $precioUnitario,
                    'costo_flete_por_tonelada' => $costoFlete,
                    // COSTOS DEL CARBON
                    'precio_materia_prima' => $precioMateriaPrima,
                    'subtotal' => $subtotal,
                    'monto_igv' => $montoIgv,
                    'total' => $total,
                    'monto_detraccion' => $montoDetraccion,
                    'neto_a_pagar' => $netoAPagar,
                    // COSTOS DE TRANSPORTE
                    'flete_subtotal' => $fleteSubtotal,
                    'flete_igv' => $fleteIgv,
                    'flete_total' => $fleteTotal,
                    'flete_monto_detraccion' => $fleteMontoDetraccion,
                    'flete_neto_a_pagar' => $fleteNetoAPagar,
                    'evidencias' => $evidenciasJson,
                    'log_cambios' => null,
                    'created_at' => now()->toDateTimeString(),
                    'estado' => EstadoCargaCompraCarbon::EnLiquidacion->value,
                    'calidad' => $nombreCalidad,
                ];

                if (Schema::hasColumn('carga_compra_carbon', 'id_comprobante_transporte_carbon')) {
                    $cargaDataToInsert['id_comprobante_transporte_carbon'] = null;
                }

                $idCarga = CargaCompraCarbon::insertGetId($cargaDataToInsert);

                $idsInsertados[] = $idCarga;

                // Afectar Stock y Kardex si ingresó a almacén de la empresa
                if ($idAlmacenEmpresa !== null) {
                    self::afectar_stock_ingreso_carga(
                        idAlmacen: $idAlmacenEmpresa,
                        idTipoCarbon: (int) $c['id_tipo_carbon'],
                        idCarga: $idCarga,
                        cantidad: $cantidad,
                        calidad: $nombreCalidad,
                        // El asiento se valoriza con el total de la carga.
                        costoTotal: $total,
                        fechaHoraIngreso: (string) ($c['fecha_hora_ingreso'] ?? now()->toDateTimeString())
                    );
                }
            }

            // Actualizar compra a En Liquidación si estaba en Preliminar
            DB::table('compra_carbon')
                ->where('id', $id_compra_carbon)
                ->where('estado', EstadoCompraCarbon::Preliminar->value)
                ->update(['estado' => EstadoCompraCarbon::EnLiquidacion->value]);

            return $idsInsertados;
        });
    }

    /**
     * Afecta StockCarbon y genera el movimiento de KardexCarbon por ingreso de carga.
     *
     * El stock se lleva por (almacen, tipo de carbon, calidad): una carga de 37 TN
     * de 'Cisco' con 12% de ceniza suma al stock de calidad 'Baja', no al global.
     */
    public static function afectar_stock_ingreso_carga(
        int $idAlmacen,
        int $idTipoCarbon,
        int $idCarga,
        float $cantidad,
        ?string $calidad,
        float $costoTotal,
        string $fechaHoraIngreso
    ): void {
        $stock = DB::table('stock_carbon')
            ->where('id_almacen', $idAlmacen)
            ->where('id_tipo_carbon', $idTipoCarbon)
            ->where(function ($q) use ($calidad) {
                $calidad === null
                    ? $q->whereNull('calidad')
                    : $q->where('calidad', $calidad);
            })
            ->lockForUpdate()
            ->first();

        $stockAnterior = $stock !== null ? (float) $stock->stock_actual : 0.0;
        $stockResultante = round($stockAnterior + $cantidad, 4);

        if ($stock === null) {
            StockCarbon::insert([
                'id_almacen' => $idAlmacen,
                'id_tipo_carbon' => $idTipoCarbon,
                'calidad' => $calidad,
                'stock_actual' => $stockResultante,
                'cambios_log' => null,
            ]);
        } else {
            DB::table('stock_carbon')
                ->where('id', $stock->id)
                ->update(['stock_actual' => $stockResultante]);
        }

        KardexCarbon::insert([
            'id_almacen' => $idAlmacen,
            'id_tipo_carbon' => $idTipoCarbon,
            'id_carga_compra_carbon' => $idCarga,
            'fecha_hora_movimiento' => $fechaHoraIngreso,
            'tipo_movimiento' => 'Ingreso',
            'calidad' => $calidad,
            'stock_anterior' => $stockAnterior,
            'cantidad_movimiento' => $cantidad,
            'stock_resultante' => $stockResultante,
            'costo_total' => $costoTotal,
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Revierte un movimiento de stock/kardex previamente aplicado a una carga.
     * Usado por la edicion de carga cuando cambian campos stock-affecting
     * (id_almacen_empresa_llegada, id_tipo_carbon, cantidad). El Kardex es
     * inmutable, asi que la "anulacion" es un movimiento `Salida` adicional
     * que compensa exactamente el `Ingreso` original de la carga.
     *
     * Precondicion: el caller ya tiene lock sobre la fila de carga y ha leido
     * el stock actual antes de llamar.
     *
     * Public para que `CompraCarbonService::actualizar_carga` pueda invocarla
     * al revertir el movimiento viejo antes de aplicar el nuevo.
     */
    public static function revertir_stock_ingreso_carga(
        int $idAlmacen,
        int $idTipoCarbon,
        ?string $calidad,
        int $idCarga,
        float $cantidad,
        float $costoTotal,
        string $fechaHoraMovimiento
    ): void {
        // El stock se lleva por (almacen, tipo de carbon, calidad): hay que
        // reversar EXACTAMENTE la fila que se sumo al ingresar la carga, por
        // eso se filtra tambien por calidad.
        $stock = DB::table('stock_carbon')
            ->where('id_almacen', $idAlmacen)
            ->where('id_tipo_carbon', $idTipoCarbon)
            ->where(function ($q) use ($calidad) {
                $calidad === null
                    ? $q->whereNull('calidad')
                    : $q->where('calidad', $calidad);
            })
            ->lockForUpdate()
            ->first();

        // Si por alguna razon no existe la fila de stock (corrupcion), abortamos
        // para no dejar stock negativo silenciosamente.
        if ($stock === null) {
            throw new \RuntimeException(
                "No existe fila de stock_carbon para almacen {$idAlmacen} / tipo {$idTipoCarbon}. No se puede revertir la carga {$idCarga}.",
            );
        }

        $stockAnterior = (float) $stock->stock_actual;
        $stockResultante = round($stockAnterior - $cantidad, 4);

        DB::table('stock_carbon')
            ->where('id', $stock->id)
            ->update(['stock_actual' => $stockResultante]);

        KardexCarbon::insert([
            'id_almacen' => $idAlmacen,
            'id_tipo_carbon' => $idTipoCarbon,
            'id_carga_compra_carbon' => $idCarga,
            'fecha_hora_movimiento' => $fechaHoraMovimiento,
            'tipo_movimiento' => 'Salida',
            'calidad' => $calidad,
            'stock_anterior' => $stockAnterior,
            'cantidad_movimiento' => $cantidad,
            'stock_resultante' => $stockResultante,
            'costo_total' => $costoTotal,
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Revalora el asiento de Kardex de una carga de compra.
     *
     * Solo se toca el movimiento de Ingreso: los movimientos compensatorios
     * (Salida) conservan su valor porque el Kardex es inmutable.
     */
    public static function actualizar_costo_kardex_carga(int $idCarga, float $costoTotal): void
    {
        DB::table('kardex_carbon')
            ->where('id_carga_compra_carbon', $idCarga)
            ->where('tipo_movimiento', 'Ingreso')
            ->update(['costo_total' => $costoTotal]);
    }

    /**
     * Cierra la orden de compra.
     */
    public static function cerrar_compra(int $id_compra_carbon, int $id_empleado): void
    {
        DB::table('compra_carbon')
            ->where('id', $id_compra_carbon)
            ->update([
                'estado' => EstadoCompraCarbon::Cerrado->value,
                'id_empleado_cierre' => $id_empleado,
                'fecha_hora_cierre' => now()->toDateTimeString(),
            ]);
    }

    /**
     * Anula una orden de compra siempre que no tenga cargas activas ni pagos.
     */
    public static function anular_compra(int $id_compra_carbon, int $id_empleado): void
    {
        DB::table('compra_carbon')
            ->where('id', $id_compra_carbon)
            ->update([
                'estado' => EstadoCompraCarbon::Anulado->value,
                'id_empleado_anula' => $id_empleado,
                'fecha_hora_anulacion' => now()->toDateTimeString(),
            ]);
    }

    /**
     * Obtiene un pago al proveedor por su ID con sus relaciones y anticipos aplicados.
     */
    public static function get_pago_proveedor_by_id(int $id_pago): ?object
    {
        $rows = DB::select('
            SELECT
                p.id AS id_pago_compra_carbon,
                p.id_compra_carbon,
                p.id_comprobante_compra_carbon,
                p.id_cuenta_bancaria_empresa,
                bce.nombre AS empresa_banco,
                cbe.numero_cuenta AS empresa_numero_cuenta,
                p.id_cuenta_bancaria_proveedor,
                bcp.nombre AS proveedor_banco,
                cbp.numero_cuenta AS proveedor_numero_cuenta,
                p.id_empleado_registro,
                CONCAT(ep.nombre, " ", ep.apellido) AS empleado_registro,
                p.medio_pago,
                p.numero_operacion,
                p.fecha_hora_pago,
                p.es_para_detraccion,
                p.observacion,
                p.evidencias,
                p.monto_pagado,
                p.created_at
            FROM pago_compra_carbon p
            LEFT JOIN cuenta_bancaria_empresa cbe ON cbe.id = p.id_cuenta_bancaria_empresa
            LEFT JOIN banco bce ON bce.id = cbe.id_banco
            LEFT JOIN cuenta_bancaria_proveedor cbp ON cbp.id = p.id_cuenta_bancaria_proveedor
            LEFT JOIN banco bcp ON bcp.id = cbp.id_banco
            INNER JOIN empleado ep ON ep.id = p.id_empleado_registro
            WHERE p.id = :id
        ', ['id' => $id_pago]);

        if (empty($rows)) {
            return null;
        }

        $p = $rows[0];
        $p->evidencias = self::decode_json($p->evidencias ?? null);
        $p->anticipos_aplicados = DB::select('
            SELECT
                tap.id AS id_transaccion,
                tap.id_anticipo_proveedor,
                tap.monto_retirado,
                ap.medio_pago,
                ap.numero_operacion,
                ap.fecha_hora_pago,
                ap.codigo_comprobante
            FROM transaccion_anticipo_proveedor tap
            INNER JOIN anticipo_proveedor ap ON ap.id = tap.id_anticipo_proveedor
            WHERE tap.id_pago_compra_carbon = :id_pago
        ', ['id_pago' => $id_pago]);

        return $p;
    }

    /**
     * Obtiene un comprobante del proveedor por su ID con sus pagos y anticipos aplicados.
     */
    public static function get_comprobante_proveedor_by_id(int $id_comprobante): ?object
    {
        $rows = DB::select('
            SELECT
                cmp.id AS id_comprobante_compra_carbon,
                cmp.id_empleado_registro,
                CONCAT(e.nombre, " ", e.apellido) AS empleado_registro,
                cmp.id_compra_carbon,
                cmp.codigo_comprobante,
                cmp.fecha_emision,
                cmp.observacion,
                cmp.evidencias,
                cmp.subtotal,
                cmp.monto_pagado_anticipos,
                cmp.base_imponible,
                cmp.monto_igv,
                cmp.importe_total,
                cmp.monto_detraccion,
                cmp.monto_neto,
                cmp.avance_pago_detraccion,
                cmp.avance_pago_neto,
                cmp.created_at,
                cmp.estado
            FROM comprobante_compra_carbon cmp
            INNER JOIN empleado e ON e.id = cmp.id_empleado_registro
            WHERE cmp.id = :id
        ', ['id' => $id_comprobante]);

        if (empty($rows)) {
            return null;
        }

        $cmp = $rows[0];
        $cmp->evidencias = self::decode_json($cmp->evidencias ?? null);
        $cmp->pagos = DB::select('
            SELECT
                p.id AS id_pago_compra_carbon,
                p.id_compra_carbon,
                p.id_comprobante_compra_carbon,
                p.id_cuenta_bancaria_empresa,
                bce.nombre AS empresa_banco,
                cbe.numero_cuenta AS empresa_numero_cuenta,
                p.id_cuenta_bancaria_proveedor,
                bcp.nombre AS proveedor_banco,
                cbp.numero_cuenta AS proveedor_numero_cuenta,
                p.id_empleado_registro,
                CONCAT(ep.nombre, " ", ep.apellido) AS empleado_registro,
                p.medio_pago,
                p.numero_operacion,
                p.fecha_hora_pago,
                p.es_para_detraccion,
                p.observacion,
                p.evidencias,
                p.monto_pagado,
                p.created_at
            FROM pago_compra_carbon p
            LEFT JOIN cuenta_bancaria_empresa cbe ON cbe.id = p.id_cuenta_bancaria_empresa
            LEFT JOIN banco bce ON bce.id = cbe.id_banco
            LEFT JOIN cuenta_bancaria_proveedor cbp ON cbp.id = p.id_cuenta_bancaria_proveedor
            LEFT JOIN banco bcp ON bcp.id = cbp.id_banco
            INNER JOIN empleado ep ON ep.id = p.id_empleado_registro
            WHERE p.id_comprobante_compra_carbon = :id_cmp
            ORDER BY p.id ASC
        ', ['id_cmp' => $id_comprobante]);
        foreach ($cmp->pagos as $p) {
            $p->evidencias = self::decode_json($p->evidencias ?? null);
        }

        $cmp->anticipos_aplicados = DB::select('
            SELECT
                tap.id AS id_transaccion,
                tap.id_anticipo_proveedor,
                tap.monto_retirado,
                ap.medio_pago,
                ap.numero_operacion,
                ap.fecha_hora_pago,
                ap.codigo_comprobante
            FROM transaccion_anticipo_proveedor tap
            INNER JOIN anticipo_proveedor ap ON ap.id = tap.id_anticipo_proveedor
            WHERE tap.id_comprobante_compra_carbon = :id_cmp
        ', ['id_cmp' => $id_comprobante]);

        return $cmp;
    }

    /**
     * Obtiene un comprobante de transporte por su ID con sus pagos.
     */
    public static function get_comprobante_transporte_by_id(int $id_comprobante): ?object
    {
        $rows = DB::select('
            SELECT
                ct.id AS id_comprobante_transporte_carbon,
                ct.id_compra_carbon,
                ct.id_empleado_registro,
                CONCAT(e.nombre, " ", e.apellido) AS empleado_registro,
                ct.id_transportista,
                tr.razon_social AS transportista_razon_social,
                ct.codigo_comprobante,
                ct.fecha_emision,
                ct.observacion,
                ct.evidencias,
                ct.subtotal,
                ct.monto_igv,
                ct.importe_total,
                ct.monto_detraccion,
                ct.monto_neto,
                ct.avance_pago_detraccion,
                ct.avance_pago_neto,
                ct.created_at,
                ct.estado
            FROM comprobante_transporte_carbon ct
            INNER JOIN empleado e ON e.id = ct.id_empleado_registro
            INNER JOIN transportista tr ON tr.id = ct.id_transportista
            WHERE ct.id = :id
        ', ['id' => $id_comprobante]);

        if (empty($rows)) {
            return null;
        }

        $ct = $rows[0];
        $ct->evidencias = self::decode_json($ct->evidencias ?? null);
        $ct->pagos = DB::select('
            SELECT
                p.id AS id_pago_transporte_carbon,
                p.id_compra_carbon,
                p.id_comprobante_transporte_carbon,
                p.id_cuenta_bancaria_empresa,
                bce.nombre AS empresa_banco,
                cbe.numero_cuenta AS empresa_numero_cuenta,
                p.id_cuenta_bancaria_transportista,
                bct.nombre AS transportista_banco,
                cbt.numero_cuenta AS transportista_numero_cuenta,
                p.id_empleado_registro,
                CONCAT(ep.nombre, " ", ep.apellido) AS empleado_registro,
                p.medio_pago,
                p.numero_operacion,
                p.fecha_hora_pago,
                p.es_para_detraccion,
                p.observacion,
                p.evidencias,
                p.monto_pagado,
                p.created_at
            FROM pago_transporte_carbon p
            LEFT JOIN cuenta_bancaria_empresa cbe ON cbe.id = p.id_cuenta_bancaria_empresa
            LEFT JOIN banco bce ON bce.id = cbe.id_banco
            LEFT JOIN cuenta_bancaria_transportista cbt ON cbt.id = p.id_cuenta_bancaria_transportista
            LEFT JOIN banco bct ON bct.id = cbt.id_banco
            INNER JOIN empleado ep ON ep.id = p.id_empleado_registro
            WHERE p.id_comprobante_transporte_carbon = :id_ct
            ORDER BY p.id ASC
        ', ['id_ct' => $id_comprobante]);
        foreach ($ct->pagos as $p) {
            $p->evidencias = self::decode_json($p->evidencias ?? null);
        }

        return $ct;
    }

    /**
     * Obtiene un pago de transporte por su ID.
     */
    public static function get_pago_transporte_by_id(int $id_pago): ?object
    {
        $rows = DB::select('
            SELECT
                p.id AS id_pago_transporte_carbon,
                p.id_compra_carbon,
                p.id_comprobante_transporte_carbon,
                p.id_cuenta_bancaria_empresa,
                bce.nombre AS empresa_banco,
                cbe.numero_cuenta AS empresa_numero_cuenta,
                p.id_cuenta_bancaria_transportista,
                bct.nombre AS transportista_banco,
                cbt.numero_cuenta AS transportista_numero_cuenta,
                p.id_empleado_registro,
                CONCAT(ep.nombre, " ", ep.apellido) AS empleado_registro,
                p.medio_pago,
                p.numero_operacion,
                p.fecha_hora_pago,
                p.es_para_detraccion,
                p.observacion,
                p.evidencias,
                p.monto_pagado,
                p.created_at
            FROM pago_transporte_carbon p
            LEFT JOIN cuenta_bancaria_empresa cbe ON cbe.id = p.id_cuenta_bancaria_empresa
            LEFT JOIN banco bce ON bce.id = cbe.id_banco
            LEFT JOIN cuenta_bancaria_transportista cbt ON cbt.id = p.id_cuenta_bancaria_transportista
            LEFT JOIN banco bct ON bct.id = cbt.id_banco
            INNER JOIN empleado ep ON ep.id = p.id_empleado_registro
            WHERE p.id = :id
        ', ['id' => $id_pago]);

        if (empty($rows)) {
            return null;
        }

        $p = $rows[0];
        $p->evidencias = self::decode_json($p->evidencias ?? null);
        return $p;
    }

    /**
     * Helper para decodificar JSON sin errores.
     */
    public static function decode_json(mixed $val): mixed
    {
        if (is_array($val) || is_object($val)) {
            return $val;
        }
        if (is_string($val) && $val !== '') {
            $dec = json_decode($val, true);
            return json_last_error() === JSON_ERROR_NONE ? $dec : [];
        }
        return [];
    }

    /**
     * Actualiza los campos editables de una compra de carbon. NO toca
     * `estado`, `correlativo`, `numero_correlativo`, ni los ids de empleado
     * (registro / cierre / anulacion). El caller es responsable de validar
     * que la compra este en estado modificable antes de llamar.
     *
     * @param array<string, mixed> $data
     */
    public static function update_compra(int $id_compra_carbon, array $data): int
    {
        $payload = [];
        $camposPermitidos = [
            'id_empresa',
            'id_proveedor',
            'id_tipo_carbon_prometido',
            'id_tarifa_carbon',
            'id_cuenta_proveedor',
            'aplica_igv',
            'porcentaje_igv',
            'porcentaje_detraccion',
            'toneladas_prometidas',
            'precio_unitario_cotizado',
            // Montos derivados: los recalcula el caller con las mismas formulas
            // que usa el registro (crear_compra).
            'subtotal_cotizado',
            'monto_igv_cotizado',
            'total_cotizado',
            'monto_detraccion_cotizado',
            'neto_a_pagar_cotizado',
        ];

        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $data)) {
                $payload[$campo] = $data[$campo];
            }
        }

        if (empty($payload)) {
            return 0;
        }

        return DB::table('compra_carbon')
            ->where('id', $id_compra_carbon)
            ->update($payload);
    }

    /**
     * Append de un evento al log de cambios de una compra (json column).
     */
    public static function append_log_cambio_compra(int $id_compra_carbon, string $event): void
    {
        $actual = DB::table('compra_carbon')
            ->where('id', $id_compra_carbon)
            ->value('log_cambios');

        $log = self::decode_json($actual) ?? [];
        if (!is_array($log)) {
            $log = [];
        }
        $log[] = json_decode($event, true) ?? $event;
        DB::table('compra_carbon')
            ->where('id', $id_compra_carbon)
            ->update([
                    'log_cambios' => json_encode($log, JSON_UNESCAPED_UNICODE),
                ]);
    }

    /**
     * Obtiene una carga por id. Usado por la logica de edicion para conocer
     * el estado previo de stock antes de revertir.
     */
    public static function get_carga_by_id(int $id_carga_compra_carbon): ?object
    {
        $rows = DB::select('
            SELECT
                c.id AS id_carga_compra_carbon,
                c.id_compra_carbon,
                c.id_tipo_carbon,
                c.id_almacen_empresa_llegada,
                c.cantidad,
                c.precio_unitario,
                c.estado,
                c.id_comprobante_compra_carbon,
                c.id_pago_compra_carbon,
                c.id_comprobante_transporte_carbon,
                c.fecha_hora_ingreso,
                c.porcentaje_ceniza,
                c.calidad,
                c.pagar_flete,
                c.costo_flete_por_tonelada
            FROM carga_compra_carbon c
            WHERE c.id = :id
            LIMIT 1
        ', ['id' => $id_carga_compra_carbon]);
        return $rows[0] ?? null;
    }

    /**
     * Actualiza los campos editables de una carga. NO toca estado, ni los ids
     * de comprobante / pago (esas relaciones son inmutables salvo anulacion
     * explicita). Si el cambio incluye campos stock-affecting
     * (id_almacen_empresa_llegada, id_tipo_carbon, cantidad) el caller debe
     * haber revertido y reaplicado el stock en la misma transaccion.
     *
     * @param array<string, mixed> $data
     */
    public static function update_carga(int $id_carga_compra_carbon, array $data): int
    {
        $payload = [];
        $camposPermitidos = [
            'id_tipo_carbon',
            'id_lugar_extraccion',
            'id_almacen_proveedor_recojo',
            'id_almacen_empresa_llegada',
            'id_almacen_cliente_llegada',
            'id_tarifa_carbon',
            'id_transportista',
            'tipo_despacho',
            'placa',
            'fecha_hora_ingreso',
            'guia_remitente',
            'guia_transportista',
            'pagar_flete',
            'codigo_ticket_balanza',
            'cantidad',
            'porcentaje_ceniza',
            'porcentaje_humedad',
            'precio_unitario',
            'costo_flete_por_tonelada',
            // Montos derivados: los recalcula el caller con las mismas formulas
            // que usa el registro (insert_cargas).
            'calidad',
            'precio_materia_prima',
            'subtotal',
            'monto_igv',
            'total',
            'monto_detraccion',
            'neto_a_pagar',
            'flete_subtotal',
            'flete_igv',
            'flete_total',
            'flete_monto_detraccion',
            'flete_neto_a_pagar',
            'evidencias',
        ];

        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $data)) {
                $payload[$campo] = $data[$campo];
            }
        }

        if (empty($payload)) {
            return 0;
        }

        return DB::table('carga_compra_carbon')
            ->where('id', $id_carga_compra_carbon)
            ->update($payload);
    }

    /**
     * Append de un evento al log de cambios de una carga (json column).
     */
    public static function append_log_cambio_carga(int $id_carga_compra_carbon, string $event): void
    {
        $actual = DB::table('carga_compra_carbon')
            ->where('id', $id_carga_compra_carbon)
            ->value('log_cambios');

        $log = self::decode_json($actual) ?? [];
        if (!is_array($log)) {
            $log = [];
        }
        $log[] = json_decode($event, true) ?? $event;
        DB::table('carga_compra_carbon')
            ->where('id', $id_carga_compra_carbon)
            ->update([
                    'log_cambios' => json_encode($log, JSON_UNESCAPED_UNICODE),
                ]);
    }
}
