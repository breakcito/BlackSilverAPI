<?php

namespace App\Modules\CompraCarbon\Data;

use Illuminate\Support\Facades\DB;

class CompraCarbonReporteData
{
    /**
     * Cargas de carbón ingresadas en un año, con todo lo necesario para el
     * "Registro de Compras" (Control de Compras).
     *
     * El corte es por `YEAR(c.fecha_hora_ingreso)`: el reporte lista las
     * cargas que han ingresado al almacén, no las órdenes creadas.
     *
     * Cada fila trae:
     *  - la carga con sus importes (materia prima y flete),
     *  - la cabecera de la OC (proveedor, porcentajes, empresa),
     *  - el comprobante del proveedor (fecha, código e importes),
     *  - el PRIMER pago que NO es para detracción (directo o del comprobante),
     *  - el comprobante de transporte y su primer pago no-detracción.
     *
     * @return array<int, object>
     */
    public static function get_cargas_control_compras(int $anio): array
    {
        $sql = '
            SELECT
                c.id AS id_carga_compra_carbon,
                c.fecha_hora_ingreso,
                c.codigo_ticket_balanza,
                c.placa,
                c.guia_remitente,
                c.guia_transportista,
                c.tipo_despacho,
                c.cantidad,
                c.porcentaje_ceniza,
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
                c.pagar_flete,
                c.estado,

                tc.nombre AS tipo_carbon_nombre,
                tc.codigo AS tipo_carbon_codigo,
                le.direccion AS lugar_extraccion,

                alm.nombre AS almacen_empresa_nombre,
                ac.direccion AS almacen_cliente_direccion,
                cli.razon_social AS cliente_destino,

                cc.id AS id_compra_carbon,
                cc.correlativo,
                cc.aplica_igv,
                cc.porcentaje_igv,
                cc.porcentaje_detraccion,
                cc.porcentaje_igv_flete,
                cc.porcentaje_detraccion_flete,

                p.razon_social AS proveedor,
                p.ruc AS proveedor_ruc,
                p.dni AS proveedor_dni,
                e.id AS id_empresa,
                e.razon_social AS empresa,

                tr.razon_social AS transportista_razon_social,
                tr.ruc AS transportista_ruc,
                tr.dni AS transportista_dni,

                com_pr.id AS id_comprobante_compra_carbon,
                com_pr.codigo_comprobante AS comprobante_codigo,
                com_pr.fecha_emision AS comprobante_fecha_emision,

                pg.fecha_hora_pago AS pago_fecha_hora,
                pg.medio_pago AS pago_medio,
                pg.numero_operacion AS pago_numero_operacion,
                bcp.nombre AS pago_proveedor_banco,
                cbp.numero_cuenta AS pago_proveedor_numero_cuenta,

                com_tr.id AS id_comprobante_transporte_carbon,
                com_tr.codigo_comprobante AS comprobante_transporte_codigo,
                com_tr.fecha_emision AS comprobante_transporte_fecha_emision,

                pt.fecha_hora_pago AS pago_flete_fecha_hora,
                pt.medio_pago AS pago_flete_medio,
                pt.numero_operacion AS pago_flete_numero_operacion,
                bct.nombre AS pago_flete_banco,
                cbt.numero_cuenta AS pago_flete_numero_cuenta
            FROM carga_compra_carbon c
            INNER JOIN compra_carbon cc ON cc.id = c.id_compra_carbon
            INNER JOIN proveedor p ON p.id = cc.id_proveedor
            INNER JOIN empresa e ON e.id = cc.id_empresa
            INNER JOIN tipo_carbon tc ON tc.id = c.id_tipo_carbon
            LEFT JOIN lugar_extraccion_carbon le ON le.id = c.id_lugar_extraccion
            LEFT JOIN almacen alm ON alm.id = c.id_almacen_empresa_llegada
            LEFT JOIN almacen_carbon_cliente ac ON ac.id = c.id_almacen_cliente_llegada
            LEFT JOIN cliente cli ON cli.id = ac.id_cliente
            LEFT JOIN transportista tr ON tr.id = c.id_transportista
            LEFT JOIN comprobante_compra_carbon com_pr ON com_pr.id = c.id_comprobante_compra_carbon
            LEFT JOIN comprobante_transporte_carbon com_tr ON com_tr.id = c.id_comprobante_transporte_carbon
            LEFT JOIN pago_compra_carbon pg ON pg.id = (
                SELECT p2.id
                FROM pago_compra_carbon p2
                WHERE p2.es_para_detraccion = 0
                  AND (
                    (
                        c.id_comprobante_compra_carbon IS NOT NULL
                        AND p2.id_comprobante_compra_carbon = c.id_comprobante_compra_carbon
                    )
                    OR
                    (
                        c.id_comprobante_compra_carbon IS NULL
                        AND p2.id_compra_carbon = c.id_compra_carbon
                        AND p2.id_comprobante_compra_carbon IS NULL
                    )
                  )
                ORDER BY p2.fecha_hora_pago ASC, p2.id ASC
                LIMIT 1
            )
            LEFT JOIN cuenta_bancaria_proveedor cbp ON cbp.id = pg.id_cuenta_bancaria_proveedor
            LEFT JOIN banco bcp ON bcp.id = cbp.id_banco
            LEFT JOIN pago_transporte_carbon pt ON pt.id = (
                SELECT p3.id
                FROM pago_transporte_carbon p3
                WHERE p3.es_para_detraccion = 0
                  AND p3.id_comprobante_transporte_carbon = c.id_comprobante_transporte_carbon
                ORDER BY p3.fecha_hora_pago ASC, p3.id ASC
                LIMIT 1
            )
            LEFT JOIN cuenta_bancaria_transportista cbt ON cbt.id = pt.id_cuenta_bancaria_transportista
            LEFT JOIN banco bct ON bct.id = cbt.id_banco
            WHERE YEAR(c.fecha_hora_ingreso) = :anio
              AND c.estado <> "Anulado"
            ORDER BY c.fecha_hora_ingreso ASC, c.id ASC
        ';

        return DB::select($sql, ['anio' => $anio]);
    }
}
