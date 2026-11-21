<?php

namespace App\Modules\Balances\Data;

use Illuminate\Support\Facades\DB;

/**
 * Consulta que reune TODOS los movimientos economicos del sistema en una
 * sola lista plana ("libro mayor" del balance).
 *
 * Cada rama del UNION representa un ORIGEN (ver `OrigenBalance`) y trae
 * exactamente las mismas columnas, para que el front pueda resumir por
 * moneda, ambito y categoria sin conocer la base de datos.
 *
 * El periodo se inyecta una sola vez por CTE (`periodo`) y cada rama hace
 * `CROSS JOIN periodo`. Asi los parametros named no se repiten y el motor
 * puede reutilizar el rango en todas las ramas.
 *
 * Reglas aplicadas (heredadas de los modulos origen):
 * - Se excluyen los registros anulados de cada modulo (estado <> 'Anulado',
 *   estado <> 'Eliminado').
 * - `gasto_despacho` no tiene columna de fecha: se usa la del despacho
 *   (`despacho_mineral.fecha_despacho`) y, en su defecto, `created_at`.
 * - Los comprobantes de flete conservan su moneda original (`moneda`) y la
 *   liquidacion de mineral es siempre en dolares. NO se mezclan monedas:
 *   cada movimiento se acumula en la suya.
 */
class BalancesData
{
    /**
     * Consulta unificada de los 13 origenes del balance.
     *
     * @param string $fecha_inicio Fecha inicial del periodo (Y-m-d).
     * @param string $fecha_fin    Fecha final del periodo (Y-m-d).
     */
    public static function get_movimientos(string $fecha_inicio, string $fecha_fin): array
    {
        $sql = self::sql_movimientos();

        $rows = DB::select($sql, [
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
        ]);

        return array_map([self::class, 'normalizar'], $rows);
    }

    /**
     * Normaliza una fila cruda de la consulta a los tipos que espera el
     * front (numeros como float, ids como int, textos como string).
     */
    private static function normalizar(object $row): array
    {
        return [
            'id_origen' => (string) $row->id_origen,
            'origen' => (string) $row->origen,
            'ambito' => (string) $row->ambito,
            'tipo' => (string) $row->tipo,
            'fecha' => (string) $row->fecha,
            'descripcion' => (string) ($row->descripcion ?? ''),
            'referencia' => $row->referencia !== null ? (string) $row->referencia : null,
            'detalle' => $row->detalle !== null ? (string) $row->detalle : null,
            'id_almacen' => $row->id_almacen !== null ? (int) $row->id_almacen : null,
            'id_cliente' => $row->id_cliente !== null ? (int) $row->id_cliente : null,
            'id_proveedor' => $row->id_proveedor !== null ? (int) $row->id_proveedor : null,
            'id_empleado' => $row->id_empleado !== null ? (int) $row->id_empleado : null,
            'moneda' => (string) $row->moneda,
            'monto' => (float) $row->monto,
            'tipo_cambio' => (float) $row->tipo_cambio,
        ];
    }

    /**
     * SQL con el UNION ALL de los 13 origenes.
     */
    private static function sql_movimientos(): string
    {
        return '
        WITH periodo AS (
            SELECT
                CAST(:fecha_inicio AS DATE) AS f_ini,
                DATE_ADD(CAST(:fecha_fin AS DATE), INTERVAL 1 DAY) AS f_fin_excl
        )

        -- 1) MINERAL / EGRESO - Consumo de inventario
        SELECT
            CAST(CONCAT("CONSUMO_MINERAL-", c.id) AS CHAR(96))          AS id_origen,
            CAST("CONSUMO_MINERAL" AS CHAR(32))                          AS origen,
            CAST("MINERAL" AS CHAR(16))                                  AS ambito,
            CAST("EGRESO" AS CHAR(16))                                   AS tipo,
            DATE(c.fecha_hora_consumo)                                   AS fecha,
            CAST(CONCAT("Consumo de ", COALESCE(pr.nombre, "producto")) AS CHAR(512)) AS descripcion,
            CAST(COALESCE(rq.correlativo, lp.correlativo) AS CHAR(255))  AS referencia,
            CAST(CONCAT_WS(" | ",
                NULLIF(pr.nombre, ""),
                CASE WHEN c.cantidad_base_consumida IS NOT NULL
                     THEN CONCAT(FORMAT(c.cantidad_base_consumida, 2), " und. base") END,
                CASE WHEN act.correlativo IS NOT NULL
                     THEN CONCAT("Activo: ", act.correlativo) END,
                CASE WHEN lm.codigo IS NOT NULL
                     THEN CONCAT("Lote mineral: ", lm.codigo) END
            ) AS CHAR(512))                                              AS detalle,
            COALESCE(c.id_almacen, rq.id_almacen_destino)                AS id_almacen,
            CAST(NULL AS SIGNED)                                         AS id_cliente,
            CAST(NULL AS SIGNED)                                         AS id_proveedor,
            c.id_empleado_registro                                       AS id_empleado,
            CAST("Soles" AS CHAR(16))                                     AS moneda,
            ROUND(c.cantidad_base_consumida * COALESCE(
                NULLIF(lp.costo_promedio_base, 0),
                NULLIF(lp.costo_por_unidad, 0) / NULLIF(lp.contenido_por_presentacion, 0),
                0
            ), 2)                                                        AS monto,
            CAST(1 AS DECIMAL(18,6))                                     AS tipo_cambio
        FROM requerimiento_almacen_entrega_detalle_consumo c
        CROSS JOIN periodo p
        LEFT JOIN requerimiento_almacen_entrega_detalle entd ON entd.id = c.id_requerimiento_almacen_entrega_detalle
        LEFT JOIN requerimiento_almacen_detalle rad ON rad.id = entd.id_requerimiento_almacen_detalle
        LEFT JOIN requerimiento_almacen rq ON rq.id = rad.id_requerimiento_almacen
        LEFT JOIN lote_producto lp ON lp.id = COALESCE(c.id_lote_producto, entd.id_lote_producto)
        LEFT JOIN producto pr ON pr.id = COALESCE(c.id_producto, rad.id_producto)
        LEFT JOIN activo_fijo act ON act.id = c.id_activo_fijo_consumidor
        LEFT JOIN lote_mineral lm ON lm.id = c.id_lote_mineral
        WHERE c.fecha_hora_consumo >= p.f_ini
          AND c.fecha_hora_consumo <  p.f_fin_excl
          AND (rq.estado IS NULL OR rq.estado <> "Anulado")

        UNION ALL

        -- 2) MINERAL / EGRESO - Gastos extra
        SELECT
            CAST(CONCAT("GASTO_EXTRA-", g.id) AS CHAR(96))              AS id_origen,
            CAST("GASTO_EXTRA" AS CHAR(32))                              AS origen,
            CAST("MINERAL" AS CHAR(16))                                  AS ambito,
            CAST("EGRESO" AS CHAR(16))                                   AS tipo,
            COALESCE(g.fecha_gasto, DATE(g.created_at))                  AS fecha,
            CAST(COALESCE(NULLIF(g.descripcion, ""), "Gasto extra") AS CHAR(512)) AS descripcion,
            CAST(NULL AS CHAR(255))                                      AS referencia,
            CAST(CONCAT_WS(" | ",
                CASE WHEN lb.nombre IS NOT NULL THEN CONCAT("Labor: ", lb.nombre) END,
                CONCAT("Registrado por ", COALESCE(NULLIF(CONCAT(e.nombre, " ", COALESCE(e.apellido, "")), ""), "S/D"))
            ) AS CHAR(512))                                              AS detalle,
            CAST(NULL AS SIGNED)                                         AS id_almacen,
            CAST(NULL AS SIGNED)                                         AS id_cliente,
            CAST(NULL AS SIGNED)                                         AS id_proveedor,
            g.id_empleado_registro                                       AS id_empleado,
            CAST("Soles" AS CHAR(16))                                     AS moneda,
            COALESCE(g.monto, 0)                                         AS monto,
            CAST(1 AS DECIMAL(18,6))                                     AS tipo_cambio
        FROM gasto_extra g
        CROSS JOIN periodo p
        LEFT JOIN empleado e ON e.id = g.id_empleado_registro
        LEFT JOIN labor lb ON lb.id = g.id_labor
        WHERE COALESCE(g.fecha_gasto, DATE(g.created_at)) >= p.f_ini
          AND COALESCE(g.fecha_gasto, DATE(g.created_at)) <  p.f_fin_excl
          AND (g.estado IS NULL OR g.estado <> "Eliminado")

        UNION ALL

        -- 3) MINERAL / EGRESO - Mantenimiento de maquinaria
        --    `otros_gastos` es un JSON [{concepto, costo}]: se suma con JSON_TABLE.
        SELECT
            CAST(CONCAT("MANTENIMIENTO-", m.id) AS CHAR(96))            AS id_origen,
            CAST("MANTENIMIENTO" AS CHAR(32))                            AS origen,
            CAST("MINERAL" AS CHAR(16))                                  AS ambito,
            CAST("EGRESO" AS CHAR(16))                                   AS tipo,
            DATE(m.fecha_hora_mantenimiento)                             AS fecha,
            CAST(CONCAT("Mantenimiento de ", COALESCE(act.correlativo, CONCAT("activo #", m.id_activo_fijo))) AS CHAR(512)) AS descripcion,
            CAST(act.correlativo AS CHAR(255))                           AS referencia,
            CAST(CONCAT_WS(" | ",
                NULLIF(m.observacion, ""),
                CASE WHEN prv.razon_social IS NOT NULL THEN CONCAT("Proveedor: ", prv.razon_social)
                     WHEN eje.nombre IS NOT NULL THEN CONCAT("Ejecutor: ", CONCAT(eje.nombre, " ", COALESCE(eje.apellido, "")))
                     END
            ) AS CHAR(512))                                              AS detalle,
            m.id_almacen                                                 AS id_almacen,
            CAST(NULL AS SIGNED)                                         AS id_cliente,
            m.id_proveedor                                               AS id_proveedor,
            m.id_empleado_registro                                       AS id_empleado,
            CAST("Soles" AS CHAR(16))                                     AS moneda,
            ROUND(COALESCE(m.costo_mano_obra, 0) + COALESCE((
                SELECT SUM(jt.costo)
                FROM JSON_TABLE(
                    COALESCE(m.otros_gastos, JSON_ARRAY()),
                    "$[*]" COLUMNS (costo DECIMAL(18,2) PATH "$.costo")
                ) AS jt
            ), 0), 2)                                                    AS monto,
            CAST(1 AS DECIMAL(18,6))                                     AS tipo_cambio
        FROM mantenimiento_activo m
        CROSS JOIN periodo p
        LEFT JOIN activo_fijo act ON act.id = m.id_activo_fijo
        LEFT JOIN proveedor prv ON prv.id = m.id_proveedor
        LEFT JOIN empleado eje ON eje.id = m.id_empleado_ejecutor
        WHERE m.fecha_hora_mantenimiento >= p.f_ini
          AND m.fecha_hora_mantenimiento <  p.f_fin_excl

        UNION ALL

        -- 4) MINERAL / EGRESO - Gastos de despacho (winche / wincheros)
        SELECT
            CAST(CONCAT("GASTO_DESPACHO-", g.id) AS CHAR(96))           AS id_origen,
            CAST("GASTO_DESPACHO" AS CHAR(32))                           AS origen,
            CAST("MINERAL" AS CHAR(16))                                  AS ambito,
            CAST("EGRESO" AS CHAR(16))                                   AS tipo,
            COALESCE(d.fecha_despacho, DATE(g.created_at))               AS fecha,
            CAST(CONCAT("Gasto de despacho - ", COALESCE(g.tipo_gasto, "Sin tipo")) AS CHAR(512)) AS descripcion,
            CAST(CONCAT("DSP-", d.id) AS CHAR(255))                      AS referencia,
            CAST(CONCAT_WS(" | ",
                NULLIF(g.descripcion, ""),
                CASE WHEN mol.razon_social IS NOT NULL THEN CONCAT("Molino: ", mol.razon_social) END
            ) AS CHAR(512))                                              AS detalle,
            CAST(NULL AS SIGNED)                                         AS id_almacen,
            CAST(NULL AS SIGNED)                                         AS id_cliente,
            CAST(NULL AS SIGNED)                                         AS id_proveedor,
            g.id_empleado_registro                                       AS id_empleado,
            CAST("Soles" AS CHAR(16))                                     AS moneda,
            COALESCE(g.monto, 0)                                         AS monto,
            CAST(1 AS DECIMAL(18,6))                                     AS tipo_cambio
        FROM gasto_despacho g
        CROSS JOIN periodo p
        LEFT JOIN despacho_mineral d ON d.id = g.id_despacho_mineral
        LEFT JOIN molino mol ON mol.id = d.id_molino
        WHERE COALESCE(d.fecha_despacho, DATE(g.created_at)) >= p.f_ini
          AND COALESCE(d.fecha_despacho, DATE(g.created_at)) <  p.f_fin_excl

        UNION ALL

        -- 5) MINERAL / EGRESO - Flete del despacho (conserva su moneda)
        SELECT
            CAST(CONCAT("FLETE_DESPACHO-", cf.id) AS CHAR(96))           AS id_origen,
            CAST("FLETE_DESPACHO" AS CHAR(32))                           AS origen,
            CAST("MINERAL" AS CHAR(16))                                  AS ambito,
            CAST("EGRESO" AS CHAR(16))                                   AS tipo,
            DATE(cf.fecha_emision)                                       AS fecha,
            CAST(CONCAT("Flete de despacho - ", COALESCE(tm.razon_social, "transportista")) AS CHAR(512)) AS descripcion,
            CAST(COALESCE(cf.codigo_comprobante, CONCAT("DSP-", cf.id_despacho_mineral)) AS CHAR(255)) AS referencia,
            CAST(CONCAT_WS(" | ",
                CASE WHEN cf.toneladas_cobradas IS NOT NULL AND cf.toneladas_cobradas > 0
                     THEN CONCAT(FORMAT(cf.toneladas_cobradas, 2), " TN") END,
                CASE WHEN mol.razon_social IS NOT NULL THEN CONCAT("Molino: ", mol.razon_social) END
            ) AS CHAR(512))                                              AS detalle,
            CAST(NULL AS SIGNED)                                         AS id_almacen,
            CAST(NULL AS SIGNED)                                         AS id_cliente,
            CAST(NULL AS SIGNED)                                         AS id_proveedor,
            cf.id_empleado_registro                                      AS id_empleado,
            CAST(COALESCE(cf.moneda, "Soles") AS CHAR(16))               AS moneda,
            COALESCE(cf.total, 0)                                        AS monto,
            CASE WHEN COALESCE(cf.moneda, "Soles") = "Dólares"
                 THEN COALESCE(cf.tipo_cambio, 1) ELSE 1 END             AS tipo_cambio
        FROM comprobante_flete_despacho cf
        CROSS JOIN periodo p
        LEFT JOIN transportista_mineral tm ON tm.id = cf.id_transportista_mineral
        LEFT JOIN despacho_mineral d ON d.id = cf.id_despacho_mineral
        LEFT JOIN molino mol ON mol.id = d.id_molino
        WHERE cf.fecha_emision >= p.f_ini
          AND cf.fecha_emision <  p.f_fin_excl

        UNION ALL

        -- 6) MINERAL / EGRESO - Molienda
        SELECT
            CAST(CONCAT("MOLIENDA_DESPACHO-", cm.id) AS CHAR(96))       AS id_origen,
            CAST("MOLIENDA_DESPACHO" AS CHAR(32))                       AS origen,
            CAST("MINERAL" AS CHAR(16))                                  AS ambito,
            CAST("EGRESO" AS CHAR(16))                                   AS tipo,
            DATE(cm.fecha_emision)                                       AS fecha,
            CAST(CONCAT("Molienda - ", COALESCE(mol.razon_social, "molino")) AS CHAR(512)) AS descripcion,
            CAST(COALESCE(cm.codigo_comprobante, CONCAT("DSP-", cm.id_despacho_mineral)) AS CHAR(255)) AS referencia,
            CAST(CONCAT("Molino: ", COALESCE(mol.razon_social, "S/D")) AS CHAR(512)) AS detalle,
            CAST(NULL AS SIGNED)                                         AS id_almacen,
            CAST(NULL AS SIGNED)                                         AS id_cliente,
            CAST(NULL AS SIGNED)                                         AS id_proveedor,
            cm.id_empleado_registro                                      AS id_empleado,
            CAST("Soles" AS CHAR(16))                                     AS moneda,
            COALESCE(cm.total, 0)                                        AS monto,
            CAST(1 AS DECIMAL(18,6))                                     AS tipo_cambio
        FROM comprobante_molienda_despacho cm
        CROSS JOIN periodo p
        LEFT JOIN despacho_mineral d ON d.id = cm.id_despacho_mineral
        LEFT JOIN molino mol ON mol.id = d.id_molino
        WHERE cm.fecha_emision >= p.f_ini
          AND cm.fecha_emision <  p.f_fin_excl

        UNION ALL

        -- 7) MINERAL / EGRESO - Analisis de laboratorio
        --    Varios analisis pueden compartir comprobante: se agregan en un
        --    subquery GROUP BY para no multiplicar filas.
        SELECT
            CAST(CONCAT("ANALISIS_DESPACHO-", ca.id) AS CHAR(96))       AS id_origen,
            CAST("ANALISIS_DESPACHO" AS CHAR(32))                        AS origen,
            CAST("MINERAL" AS CHAR(16))                                  AS ambito,
            CAST("EGRESO" AS CHAR(16))                                   AS tipo,
            DATE(ca.fecha_emision)                                       AS fecha,
            CAST(CONCAT("Analisis de laboratorio - ", COALESCE(an.laboratorios, "laboratorio")) AS CHAR(512)) AS descripcion,
            CAST(COALESCE(ca.codigo_comprobante, CONCAT("DSP-", ca.id_despacho_mineral)) AS CHAR(255)) AS referencia,
            CAST(CONCAT_WS(" | ",
                CASE WHEN an.laboratorios IS NOT NULL THEN CONCAT("Laboratorio: ", an.laboratorios) END,
                CASE WHEN an.clientes IS NOT NULL THEN CONCAT("Cliente: ", an.clientes) END
            ) AS CHAR(512))                                              AS detalle,
            CAST(NULL AS SIGNED)                                         AS id_almacen,
            CAST(NULL AS SIGNED)                                         AS id_cliente,
            CAST(NULL AS SIGNED)                                         AS id_proveedor,
            ca.id_empleado_registro                                      AS id_empleado,
            CAST("Soles" AS CHAR(16))                                     AS moneda,
            COALESCE(ca.total, 0)                                        AS monto,
            CAST(1 AS DECIMAL(18,6))                                     AS tipo_cambio
        FROM comprobante_analisis_despacho ca
        CROSS JOIN periodo p
        LEFT JOIN (
            SELECT
                an1.id_comprobante_analisis_despacho AS id_comprobante,
                GROUP_CONCAT(DISTINCT lab.razon_social SEPARATOR ", ")     AS laboratorios,
                GROUP_CONCAT(DISTINCT cli.razon_social SEPARATOR ", ")    AS clientes
            FROM analisis_laboratorio an1
            LEFT JOIN laboratorio lab ON lab.id = an1.id_laboratorio
            LEFT JOIN cliente cli ON cli.id = an1.id_cliente
            GROUP BY an1.id_comprobante_analisis_despacho
        ) an ON an.id_comprobante = ca.id
        WHERE ca.fecha_emision >= p.f_ini
          AND ca.fecha_emision <  p.f_fin_excl

        UNION ALL

        -- 8) MINERAL / INGRESO - Alquiler de maquinaria a terceros
        --    Solo cuando el control de uso tiene cliente asignado.
        --    La fecha es el fin del control (cuando se termino el servicio).
        SELECT
            CAST(CONCAT("ALQUILER_MAQUINARIA-", cu.id) AS CHAR(96))      AS id_origen,
            CAST("ALQUILER_MAQUINARIA" AS CHAR(32))                      AS origen,
            CAST("MINERAL" AS CHAR(16))                                  AS ambito,
            CAST("INGRESO" AS CHAR(16))                                  AS tipo,
            COALESCE(DATE(cu.fecha_hora_fin_control), DATE(cu.fecha_hora_inicio_control)) AS fecha,
            CAST(CONCAT("Alquiler de ", COALESCE(act.correlativo, CONCAT("activo #", cu.id_activo_fijo))) AS CHAR(512)) AS descripcion,
            CAST(act.correlativo AS CHAR(255))                           AS referencia,
            CAST(CONCAT_WS(" | ",
                CASE WHEN cli.razon_social IS NOT NULL THEN CONCAT("Cliente: ", cli.razon_social) END,
                CASE WHEN cu.total_horas IS NOT NULL AND cu.total_horas > 0 THEN CONCAT(FORMAT(cu.total_horas, 2), " h") END,
                CASE WHEN cu.cantidad_vueltas IS NOT NULL AND cu.cantidad_vueltas > 0 THEN CONCAT(cu.cantidad_vueltas, " vueltas") END
            ) AS CHAR(512))                                              AS detalle,
            CAST(NULL AS SIGNED)                                         AS id_almacen,
            cu.id_cliente                                                 AS id_cliente,
            CAST(NULL AS SIGNED)                                         AS id_proveedor,
            CAST(NULL AS SIGNED)                                         AS id_empleado,
            CAST("Soles" AS CHAR(16))                                     AS moneda,
            COALESCE(cu.costo_total, 0)                                  AS monto,
            CAST(1 AS DECIMAL(18,6))                                     AS tipo_cambio
        FROM control_uso_activo cu
        CROSS JOIN periodo p
        LEFT JOIN activo_fijo act ON act.id = cu.id_activo_fijo
        LEFT JOIN cliente cli ON cli.id = cu.id_cliente
        WHERE cu.id_cliente IS NOT NULL
          AND (cu.estado IS NULL OR cu.estado <> "Anulado")
          AND COALESCE(cu.fecha_hora_fin_control, cu.fecha_hora_inicio_control) >= p.f_ini
          AND COALESCE(cu.fecha_hora_fin_control, cu.fecha_hora_inicio_control) <  p.f_fin_excl

        UNION ALL

        -- 9) MINERAL / INGRESO - Venta de mineral (liquidacion, siempre en dolares)
        SELECT
            CAST(CONCAT("VENTA_MINERAL-", cl.id) AS CHAR(96))            AS id_origen,
            CAST("VENTA_MINERAL" AS CHAR(32))                            AS origen,
            CAST("MINERAL" AS CHAR(16))                                  AS ambito,
            CAST("INGRESO" AS CHAR(16))                                  AS tipo,
            DATE(cl.fecha_emision)                                       AS fecha,
            CAST(CONCAT("Venta de mineral - ", COALESCE(cli.razon_social, "cliente")) AS CHAR(512)) AS descripcion,
            CAST(COALESCE(cl.codigo_comprobante, li.codigo_liquidacion) AS CHAR(255)) AS referencia,
            CAST(CONCAT_WS(" | ",
                CASE WHEN li.codigo_liquidacion IS NOT NULL THEN CONCAT("Liquidacion: ", li.codigo_liquidacion) END,
                CASE WHEN cli.razon_social IS NOT NULL THEN CONCAT("Cliente: ", cli.razon_social) END
            ) AS CHAR(512))                                              AS detalle,
            CAST(NULL AS SIGNED)                                         AS id_almacen,
            li.id_cliente                                                 AS id_cliente,
            CAST(NULL AS SIGNED)                                         AS id_proveedor,
            cl.id_empleado_registro                                      AS id_empleado,
            CAST("Dólares" AS CHAR(16))                                  AS moneda,
            COALESCE(cl.total, 0)                                        AS monto,
            COALESCE(cl.tipo_cambio, 1)                                  AS tipo_cambio
        FROM comprobante_liquidacion_despacho cl
        CROSS JOIN periodo p
        LEFT JOIN liquidacion_mineral li ON li.id = cl.id_liquidacion_mineral
        LEFT JOIN cliente cli ON cli.id = li.id_cliente
        WHERE cl.fecha_emision >= p.f_ini
          AND cl.fecha_emision <  p.f_fin_excl

        UNION ALL

        -- 10) CARBON / EGRESO - Compra de carbon
        --     El monto es el valor INTEGRO de la mercancia (carga.total, con
        --     su IGV) MENOS la parte que ya se adelanto con anticipo.
        --
        --     El anticipo se amortiza contra la liquidacion (comprobante o
        --     pago), no contra la carga. Como un anticipo puede cubrir
        --     VARIAS cargas, se reparte proporcionalmente al subtotal de
        --     cada una. Asi "Compra de carbon" + "Anticipos a proveedores"
        --     suma exactamente el valor de la mercancia: no se cuenta dos
        --     veces el mismo peso.
        SELECT
            CAST(CONCAT("COMPRA_CARBON-", t.id) AS CHAR(96))             AS id_origen,
            CAST("COMPRA_CARBON" AS CHAR(32))                            AS origen,
            CAST("CARBON" AS CHAR(16))                                   AS ambito,
            CAST("EGRESO" AS CHAR(16))                                   AS tipo,
            DATE(t.fecha_hora_ingreso)                                   AS fecha,
            CAST(CONCAT("Compra de carbon - ", COALESCE(tcb.nombre, "carbon")) AS CHAR(512)) AS descripcion,
            CAST(cc.correlativo AS CHAR(255))                            AS referencia,
            CAST(CONCAT_WS(" | ",
                CASE WHEN prv.razon_social IS NOT NULL THEN CONCAT("Proveedor: ", prv.razon_social) END,
                CASE WHEN t.placa IS NOT NULL THEN CONCAT("Placa: ", t.placa) END,
                CONCAT(FORMAT(t.cantidad, 2), " TN"),
                CASE WHEN t.anticipo_carga > 0
                     THEN CONCAT("Cubierto con anticipo por ", FORMAT(t.anticipo_carga, 2)) END
            ) AS CHAR(512))                                              AS detalle,
            t.id_almacen_empresa_llegada                                 AS id_almacen,
            CAST(NULL AS SIGNED)                                         AS id_cliente,
            cc.id_proveedor                                               AS id_proveedor,
            t.id_empleado_registro                                       AS id_empleado,
            CAST("Soles" AS CHAR(16))                                     AS moneda,
            ROUND(COALESCE(t.total, 0) - t.anticipo_carga, 2)             AS monto,
            CAST(1 AS DECIMAL(18,6))                                     AS tipo_cambio
        FROM (
            SELECT
                ccc.id,
                ccc.id_compra_carbon,
                ccc.id_empleado_registro,
                ccc.id_almacen_empresa_llegada,
                ccc.id_tipo_carbon,
                ccc.fecha_hora_ingreso,
                ccc.placa,
                ccc.cantidad,
                ccc.total,
                ccc.subtotal,
                -- Parte del anticipo que corresponde a esta carga.
                ROUND(
                    COALESCE(ac.anticipo, 0) * COALESCE(ccc.subtotal / NULLIF(sc.suma_subtotal, 0), 0)
                  + COALESCE(ap.anticipo, 0) * COALESCE(ccc.subtotal / NULLIF(sp.suma_subtotal, 0), 0)
                , 2) AS anticipo_carga
            FROM carga_compra_carbon ccc
            LEFT JOIN (
                SELECT c2.id_comprobante_compra_carbon AS id_ref, SUM(c2.subtotal) AS suma_subtotal
                FROM carga_compra_carbon c2
                WHERE c2.id_comprobante_compra_carbon IS NOT NULL
                  AND (c2.estado IS NULL OR c2.estado <> "Anulado")
                GROUP BY c2.id_comprobante_compra_carbon
            ) sc ON sc.id_ref = ccc.id_comprobante_compra_carbon
            LEFT JOIN (
                SELECT c2.id_pago_compra_carbon AS id_ref, SUM(c2.subtotal) AS suma_subtotal
                FROM carga_compra_carbon c2
                WHERE c2.id_pago_compra_carbon IS NOT NULL
                  AND (c2.estado IS NULL OR c2.estado <> "Anulado")
                GROUP BY c2.id_pago_compra_carbon
            ) sp ON sp.id_ref = ccc.id_pago_compra_carbon
            LEFT JOIN (
                SELECT id_comprobante_compra_carbon AS id_ref, SUM(monto_retirado) AS anticipo
                FROM transaccion_anticipo_proveedor
                WHERE id_comprobante_compra_carbon IS NOT NULL
                GROUP BY id_comprobante_compra_carbon
            ) ac ON ac.id_ref = ccc.id_comprobante_compra_carbon
            LEFT JOIN (
                SELECT id_pago_compra_carbon AS id_ref, SUM(monto_retirado) AS anticipo
                FROM transaccion_anticipo_proveedor
                WHERE id_pago_compra_carbon IS NOT NULL
                GROUP BY id_pago_compra_carbon
            ) ap ON ap.id_ref = ccc.id_pago_compra_carbon
            WHERE ccc.estado IS NULL OR ccc.estado <> "Anulado"
        ) t
        CROSS JOIN periodo p
        LEFT JOIN compra_carbon cc ON cc.id = t.id_compra_carbon
        LEFT JOIN proveedor prv ON prv.id = cc.id_proveedor
        LEFT JOIN tipo_carbon tcb ON tcb.id = t.id_tipo_carbon
        WHERE t.fecha_hora_ingreso >= p.f_ini
          AND t.fecha_hora_ingreso <  p.f_fin_excl

        UNION ALL

        -- 11) CARBON / EGRESO - Flete de la compra de carbon
        --     Solo se lista cuando la carga tiene flete>cero: si no, la linea
        --     seria un duplicado de la compra con monto 0.
        SELECT
            CAST(CONCAT("FLETE_COMPRA_CARBON-", ccc.id) AS CHAR(96))    AS id_origen,
            CAST("FLETE_COMPRA_CARBON" AS CHAR(32))                      AS origen,
            CAST("CARBON" AS CHAR(16))                                   AS ambito,
            CAST("EGRESO" AS CHAR(16))                                   AS tipo,
            DATE(ccc.fecha_hora_ingreso)                                 AS fecha,
            CAST(CONCAT("Flete de compra de carbon - ", COALESCE(tr.razon_social, "transportista")) AS CHAR(512)) AS descripcion,
            CAST(cc.correlativo AS CHAR(255))                            AS referencia,
            CAST(CONCAT_WS(" | ",
                CASE WHEN tr.razon_social IS NOT NULL THEN CONCAT("Transportista: ", tr.razon_social) END,
                CASE WHEN ccc.placa IS NOT NULL THEN CONCAT("Placa: ", ccc.placa) END,
                CONCAT(FORMAT(ccc.cantidad, 2), " TN")
            ) AS CHAR(512))                                              AS detalle,
            ccc.id_almacen_empresa_llegada                               AS id_almacen,
            CAST(NULL AS SIGNED)                                         AS id_cliente,
            cc.id_proveedor                                               AS id_proveedor,
            ccc.id_empleado_registro                                     AS id_empleado,
            CAST("Soles" AS CHAR(16))                                     AS moneda,
            COALESCE(ccc.flete_total, 0)                                 AS monto,
            CAST(1 AS DECIMAL(18,6))                                     AS tipo_cambio
        FROM carga_compra_carbon ccc
        CROSS JOIN periodo p
        LEFT JOIN compra_carbon cc ON cc.id = ccc.id_compra_carbon
        LEFT JOIN transportista tr ON tr.id = ccc.id_transportista
        WHERE ccc.fecha_hora_ingreso >= p.f_ini
          AND ccc.fecha_hora_ingreso <  p.f_fin_excl
          AND (ccc.estado IS NULL OR ccc.estado <> "Anulado")
          AND COALESCE(ccc.flete_total, 0) > 0

        UNION ALL

        -- 12) CARBON / EGRESO - Gastos de produccion de carbon (zarandeo / blending)
        SELECT
            CAST(CONCAT("GASTO_PRODUCCION_CARBON-", gpc.id) AS CHAR(96)) AS id_origen,
            CAST("GASTO_PRODUCCION_CARBON" AS CHAR(32))                  AS origen,
            CAST("CARBON" AS CHAR(16))                                   AS ambito,
            CAST("EGRESO" AS CHAR(16))                                   AS tipo,
            DATE(gpc.fecha)                                              AS fecha,
            CAST(CONCAT("Gasto de produccion de carbon - ", COALESCE(gpc.gasto, "Otros")) AS CHAR(512)) AS descripcion,
            CAST(COALESCE(bl.correlativo, CONCAT("ZR-", gpc.id_zarandeo_carbon)) AS CHAR(255)) AS referencia,
            CAST(CONCAT_WS(" | ",
                NULLIF(gpc.otro_gasto, ""),
                NULLIF(gpc.descripcion, ""),
                CASE WHEN prv.razon_social IS NOT NULL THEN CONCAT("Proveedor: ", prv.razon_social) END
            ) AS CHAR(512))                                              AS detalle,
            COALESCE(z.id_almacen, bl.id_almacen)                        AS id_almacen,
            CAST(NULL AS SIGNED)                                         AS id_cliente,
            gpc.id_proveedor                                              AS id_proveedor,
            gpc.id_empleado_registro                                     AS id_empleado,
            CAST("Soles" AS CHAR(16))                                     AS moneda,
            COALESCE(gpc.total, 0)                                       AS monto,
            CAST(1 AS DECIMAL(18,6))                                     AS tipo_cambio
        FROM gasto_produccion_carbon gpc
        CROSS JOIN periodo p
        LEFT JOIN zarandeo_carbon z ON z.id = gpc.id_zarandeo_carbon
        LEFT JOIN blending_carbon bl ON bl.id = gpc.id_blending_carbon
        LEFT JOIN proveedor prv ON prv.id = gpc.id_proveedor
        WHERE gpc.fecha >= p.f_ini
          AND gpc.fecha <  p.f_fin_excl

        UNION ALL

        -- 13) CARBON / EGRESO - Anticipos entregados a proveedores de carbon
        --     El monto es `saldo_inicial`: el dinero que efectivamente salio
        --     de la empresa. `saldo_actual` es lo que queda de ese anticipo
        --     (se consume al pagar compras), no el flujo del periodo.
        SELECT
            CAST(CONCAT("ANTICIPO_PROVEEDOR_CARBON-", ap.id) AS CHAR(96)) AS id_origen,
            CAST("ANTICIPO_PROVEEDOR_CARBON" AS CHAR(32))                AS origen,
            CAST("CARBON" AS CHAR(16))                                   AS ambito,
            CAST("EGRESO" AS CHAR(16))                                   AS tipo,
            COALESCE(DATE(ap.fecha_hora_pago), DATE(ap.created_at))      AS fecha,
            CAST(CONCAT("Anticipo a proveedor de carbon - ", COALESCE(prv.razon_social, "proveedor")) AS CHAR(512)) AS descripcion,
            CAST(COALESCE(NULLIF(ap.numero_operacion, ""), NULLIF(ap.codigo_comprobante, "")) AS CHAR(255)) AS referencia,
            CAST(CONCAT_WS(" | ",
                CASE WHEN ap.medio_pago IS NOT NULL THEN CONCAT("Medio de pago: ", ap.medio_pago) END,
                NULLIF(ap.observacion, ""),
                CASE WHEN IFNULL(ap.pago_a_terceros, 0) = 1 THEN "Pago a terceros" END,
                CASE WHEN tap.total_aplicado IS NOT NULL
                     THEN CONCAT("Aplicado a compras por ", FORMAT(tap.total_aplicado, 2)) END
            ) AS CHAR(512))                                              AS detalle,
            CAST(NULL AS SIGNED)                                         AS id_almacen,
            CAST(NULL AS SIGNED)                                         AS id_cliente,
            ap.id_proveedor                                               AS id_proveedor,
            ap.id_empleado_registro                                       AS id_empleado,
            CAST(COALESCE(NULLIF(cbe.moneda, ""), "Soles") AS CHAR(16))   AS moneda,
            COALESCE(ap.saldo_inicial, 0)                                 AS monto,
            CAST(1 AS DECIMAL(18,6))                                     AS tipo_cambio
        FROM anticipo_proveedor ap
        CROSS JOIN periodo p
        LEFT JOIN proveedor prv ON prv.id = ap.id_proveedor
        LEFT JOIN cuenta_bancaria_empresa cbe ON cbe.id = ap.id_cuenta_bancaria_empresa
        LEFT JOIN (
            SELECT
                t.id_anticipo_proveedor AS id_anticipo,
                SUM(t.monto_retirado) AS total_aplicado
            FROM transaccion_anticipo_proveedor t
            GROUP BY t.id_anticipo_proveedor
        ) tap ON tap.id_anticipo = ap.id
        WHERE COALESCE(DATE(ap.fecha_hora_pago), DATE(ap.created_at)) >= p.f_ini
          AND COALESCE(DATE(ap.fecha_hora_pago), DATE(ap.created_at)) <  p.f_fin_excl
          AND IFNULL(ap.esta_anulado, 0) = 0
          AND (ap.estado IS NULL OR ap.estado <> "Anulado")

        UNION ALL

        -- 14) CARBON / INGRESO - Anticipos recibidos de clientes de carbon
        --     Mismo criterio que el anticipo a proveedores: `saldo_inicial`
        --     es el dinero que entro a la empresa.
        SELECT
            CAST(CONCAT("ANTICIPO_CLIENTE_CARBON-", ac.id) AS CHAR(96))  AS id_origen,
            CAST("ANTICIPO_CLIENTE_CARBON" AS CHAR(32))                 AS origen,
            CAST("CARBON" AS CHAR(16))                                   AS ambito,
            CAST("INGRESO" AS CHAR(16))                                  AS tipo,
            COALESCE(DATE(ac.fecha_hora_pago), DATE(ac.created_at))      AS fecha,
            CAST(CONCAT("Anticipo de cliente de carbon - ", COALESCE(cli.razon_social, "cliente")) AS CHAR(512)) AS descripcion,
            CAST(COALESCE(NULLIF(ac.numero_operacion, ""), NULLIF(ac.codigo_comprobante, "")) AS CHAR(255)) AS referencia,
            CAST(CONCAT_WS(" | ",
                CASE WHEN ac.medio_pago IS NOT NULL THEN CONCAT("Medio de pago: ", ac.medio_pago) END,
                NULLIF(ac.observacion, ""),
                CASE WHEN tac.total_aplicado IS NOT NULL
                     THEN CONCAT("Aplicado a ventas por ", FORMAT(tac.total_aplicado, 2)) END
            ) AS CHAR(512))                                              AS detalle,
            CAST(NULL AS SIGNED)                                         AS id_almacen,
            ac.id_cliente                                                 AS id_cliente,
            CAST(NULL AS SIGNED)                                         AS id_proveedor,
            ac.id_empleado_registro                                       AS id_empleado,
            CAST(COALESCE(NULLIF(cbe.moneda, ""), "Soles") AS CHAR(16))   AS moneda,
            COALESCE(ac.saldo_inicial, 0)                                 AS monto,
            CAST(1 AS DECIMAL(18,6))                                     AS tipo_cambio
        FROM anticipo_cliente ac
        CROSS JOIN periodo p
        LEFT JOIN cliente cli ON cli.id = ac.id_cliente
        LEFT JOIN cuenta_bancaria_empresa cbe ON cbe.id = ac.id_cuenta_bancaria_empresa
        LEFT JOIN (
            SELECT
                t.id_anticipo_cliente AS id_anticipo,
                SUM(t.monto_retirado) AS total_aplicado
            FROM transaccion_anticipo_cliente t
            GROUP BY t.id_anticipo_cliente
        ) tac ON tac.id_anticipo = ac.id
        WHERE COALESCE(DATE(ac.fecha_hora_pago), DATE(ac.created_at)) >= p.f_ini
          AND COALESCE(DATE(ac.fecha_hora_pago), DATE(ac.created_at)) <  p.f_fin_excl
          AND IFNULL(ac.esta_anulado, 0) = 0
          AND (ac.estado IS NULL OR ac.estado <> "Anulado")

        UNION ALL

        -- 15) MINERAL / EGRESO - Gastos de transporte de la cadena de almacenes
        --     Reposiciones, entregas de reabastecimiento, entregas de
        --     prestamo y transferencias de orden de compra registran el costo
        --     del transporte hired. Solo cuenta cuando el medio de entrega es
        --     "Terceros" o "Agencia" (con medio "Propio" no hay gasto).
        SELECT
            CAST(CONCAT("TRANSPORTE-", t.origen_tabla, "-", t.id) AS CHAR(96)) AS id_origen,
            CAST("TRANSPORTE" AS CHAR(32))                               AS origen,
            CAST("MINERAL" AS CHAR(16))                                  AS ambito,
            CAST("EGRESO" AS CHAR(16))                                   AS tipo,
            t.fecha                                                      AS fecha,
            CAST(CONCAT("Gasto de transporte - ", t.tipo_movimiento) AS CHAR(512)) AS descripcion,
            CAST(t.correlativo AS CHAR(255))                             AS referencia,
            CAST(CONCAT_WS(" | ",
                CASE WHEN t.transportista IS NOT NULL THEN CONCAT("Transportista: ", t.transportista) END,
                CASE WHEN t.guia IS NOT NULL THEN CONCAT("Guia: ", t.guia) END,
                CASE WHEN t.factura IS NOT NULL THEN CONCAT("Factura: ", t.factura) END
            ) AS CHAR(512))                                              AS detalle,
            t.id_almacen                                                 AS id_almacen,
            CAST(NULL AS SIGNED)                                         AS id_cliente,
            t.id_proveedor_transporte                                    AS id_proveedor,
            t.id_empleado                                                AS id_empleado,
            CAST("Soles" AS CHAR(16))                                     AS moneda,
            t.costo_envio                                                AS monto,
            CAST(1 AS DECIMAL(18,6))                                     AS tipo_cambio
        FROM (
            SELECT
                "prestamo_almacen_reposicion" AS origen_tabla,
                r.id,
                DATE(r.fecha_hora_reposicion) AS fecha,
                r.correlativo,
                r.costo_envio,
                r.id_almacen_entrega AS id_almacen,
                r.id_proveedor_transporte,
                r.id_empleado_entrega AS id_empleado,
                "Reposicion de almacen" AS tipo_movimiento,
                COALESCE(prov_t.razon_social, age_t.razon_social) AS transportista,
                CONCAT_WS("-", NULLIF(r.serie_guia_transportista, ""), NULLIF(r.numero_guia_transportista, "")) AS guia,
                CONCAT_WS("-", NULLIF(r.serie_factura, ""), NULLIF(r.numero_factura, "")) AS factura
            FROM prestamo_almacen_reposicion r
            CROSS JOIN periodo p
            LEFT JOIN proveedor prov_t ON prov_t.id = r.id_proveedor_transporte
            LEFT JOIN agencia_transporte age_t ON age_t.id = r.id_agencia_transporte
            WHERE r.medio_entrega IN ("Terceros", "Agencia")
              AND COALESCE(r.costo_envio, 0) > 0
              AND (r.estado IS NULL OR r.estado <> "Anulado")
              AND r.fecha_hora_reposicion >= p.f_ini
              AND r.fecha_hora_reposicion <  p.f_fin_excl

            UNION ALL

            SELECT
                "solicitud_reabastecimiento_entrega",
                r.id,
                DATE(r.fecha_hora_entrega),
                r.correlativo,
                r.costo_envio,
                r.id_almacen_entrega,
                r.id_proveedor_transporte,
                r.id_empleado_entrega,
                "Entrega de reabastecimiento",
                COALESCE(prov_t.razon_social, age_t.razon_social),
                CONCAT_WS("-", NULLIF(r.serie_guia_transportista, ""), NULLIF(r.numero_guia_transportista, "")),
                CONCAT_WS("-", NULLIF(r.serie_factura, ""), NULLIF(r.numero_factura, ""))
            FROM solicitud_reabastecimiento_entrega r
            CROSS JOIN periodo p
            LEFT JOIN proveedor prov_t ON prov_t.id = r.id_proveedor_transporte
            LEFT JOIN agencia_transporte age_t ON age_t.id = r.id_agencia_transporte
            WHERE r.medio_entrega IN ("Terceros", "Agencia")
              AND COALESCE(r.costo_envio, 0) > 0
              AND (r.estado IS NULL OR r.estado <> "Anulado")
              AND r.fecha_hora_entrega >= p.f_ini
              AND r.fecha_hora_entrega <  p.f_fin_excl

            UNION ALL

            SELECT
                "prestamo_almacen_entrega",
                r.id,
                DATE(r.fecha_hora_entrega),
                r.correlativo,
                r.costo_envio,
                CAST(NULL AS SIGNED),
                r.id_proveedor_transporte,
                r.id_empleado_entrega,
                "Entrega de prestamo de almacen",
                COALESCE(prov_t.razon_social, age_t.razon_social),
                CONCAT_WS("-", NULLIF(r.serie_guia_transportista, ""), NULLIF(r.numero_guia_transportista, "")),
                CONCAT_WS("-", NULLIF(r.serie_factura, ""), NULLIF(r.numero_factura, ""))
            FROM prestamo_almacen_entrega r
            CROSS JOIN periodo p
            LEFT JOIN proveedor prov_t ON prov_t.id = r.id_proveedor_transporte
            LEFT JOIN agencia_transporte age_t ON age_t.id = r.id_agencia_transporte
            WHERE r.medio_entrega IN ("Terceros", "Agencia")
              AND COALESCE(r.costo_envio, 0) > 0
              AND (r.estado IS NULL OR r.estado <> "Anulado")
              AND r.fecha_hora_entrega >= p.f_ini
              AND r.fecha_hora_entrega <  p.f_fin_excl

            UNION ALL

            SELECT
                "orden_compra_transferencia",
                r.id,
                DATE(r.fecha_hora_transferencia),
                r.correlativo,
                r.costo_envio,
                r.id_almacen_destino,
                r.id_proveedor_transporte,
                r.id_empleado_transferencia,
                "Transferencia de orden de compra",
                COALESCE(prov_t.razon_social, age_t.razon_social),
                CONCAT_WS("-", NULLIF(r.serie_guia_transportista, ""), NULLIF(r.numero_guia_transportista, "")),
                CONCAT_WS("-", NULLIF(r.serie_factura, ""), NULLIF(r.numero_factura, ""))
            FROM orden_compra_transferencia r
            CROSS JOIN periodo p
            LEFT JOIN proveedor prov_t ON prov_t.id = r.id_proveedor_transporte
            LEFT JOIN agencia_transporte age_t ON age_t.id = r.id_agencia_transporte
            WHERE r.medio_entrega IN ("Terceros", "Agencia")
              AND COALESCE(r.costo_envio, 0) > 0
              AND (r.estado IS NULL OR r.estado <> "Anulado")
              AND r.fecha_hora_transferencia >= p.f_ini
              AND r.fecha_hora_transferencia <  p.f_fin_excl
        ) t

        UNION ALL

        -- 16) CARBON / INGRESO - Venta de carbon
        --     El monto es el valor INTEGRO de la venta (subtotal con su IGV
        --     calculado sobre el subtotal completo) MENOS la parte cobrada
        --     por adelantado con anticipo. Mismo criterio que la compra: la
        --     linea del anticipo suma lo que se cobro por adelantado y, entre
        --     las dos, dan el valor total de la venta sin contarlo dos veces.
        --
        --     Ojo: `importe_total` NO sirve aca, porque el comprobante
        --     recalcula el IGV sobre la base ya reducida por los anticipos y
        --     por eso queda con el IGV del anticipo sin facturar.
        SELECT
            CAST(CONCAT("VENTA_CARBON-", cvc.id) AS CHAR(96))            AS id_origen,
            CAST("VENTA_CARBON" AS CHAR(32))                             AS origen,
            CAST("CARBON" AS CHAR(16))                                   AS ambito,
            CAST("INGRESO" AS CHAR(16))                                  AS tipo,
            DATE(cvc.fecha_emision)                                      AS fecha,
            CAST(CONCAT("Venta de carbon - ", COALESCE(cli.razon_social, "cliente")) AS CHAR(512)) AS descripcion,
            CAST(COALESCE(cvc.codigo_comprobante, lvc.codigo_orden_compra) AS CHAR(255)) AS referencia,
            CAST(CONCAT_WS(" | ",
                CASE WHEN vc.correlativo IS NOT NULL THEN CONCAT("Venta: ", vc.correlativo) END,
                CASE WHEN cli.razon_social IS NOT NULL THEN CONCAT("Cliente: ", cli.razon_social) END,
                CASE WHEN COALESCE(cvc.monto_pagado_anticipos, 0) > 0
                     THEN CONCAT("Cubierto con anticipo por ", FORMAT(cvc.monto_pagado_anticipos, 2)) END
            ) AS CHAR(512))                                              AS detalle,
            vc.id_almacen_empresa_salida                                 AS id_almacen,
            vc.id_cliente                                                 AS id_cliente,
            CAST(NULL AS SIGNED)                                         AS id_proveedor,
            cvc.id_empleado_registro                                     AS id_empleado,
            CAST("Soles" AS CHAR(16))                                     AS moneda,
            ROUND(
                COALESCE(cvc.subtotal, 0)
                + ROUND(COALESCE(cvc.subtotal, 0) * COALESCE(vc.porcentaje_igv, 0) / 100, 2)
                - COALESCE(cvc.monto_pagado_anticipos, 0)
            , 2)                                                           AS monto,
            CAST(1 AS DECIMAL(18,6))                                     AS tipo_cambio
        FROM comprobante_venta_carbon cvc
        CROSS JOIN periodo p
        LEFT JOIN liquidacion_venta_carbon lvc ON lvc.id = cvc.id_liquidacion_venta_carbon
        LEFT JOIN venta_carbon vc ON vc.id = cvc.id_venta_carbon
        LEFT JOIN cliente cli ON cli.id = vc.id_cliente
        WHERE cvc.fecha_emision >= p.f_ini
          AND cvc.fecha_emision <  p.f_fin_excl
        ';
    }
}
