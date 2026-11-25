<?php

namespace App\Modules\Dashboard\Data;

use App\Shared\Enums\AnticipoCliente\EstadoAnticipo;
use App\Shared\Enums\VentaCarbon\EstadoComprobanteVentaCarbon;
use App\Shared\Enums\VentaMineral\EstadoComprobanteDespacho;
use Illuminate\Support\Facades\DB;

/**
 * Indicador 5: cobros pendientes (cartera por cobrar).
 *
 * ReÃºne los comprobantes EMITIDOS y no cobrados: flete, molienda, analisis y
 * liquidacion de mineral, mas la venta de carbon. El saldo pendiente es lo
 * que el cliente todavia debe.
 *
 * Reglas de la cartera:
 * - El monto que se cobra es el NETO (lo que el cliente recibe una vez
 *   descontada la detraccion), no el total con IGV.
 * - Lo pagado es el avance en firme mas lo retenido por detraccion.
 * - Los anticipos ya aplicados tambien son pago: se descuentan del saldo.
 * - Soles y dolares NO se suman: cada comprobante conserva su moneda.
 */
class CobrosPendientesData
{
    /**
     * Comprobantes emitidos y no cobrados.
     *
     * @param string $fecha_inicio Fecha inicial del periodo (Y-m-d).
     * @param string $fecha_fin    Fecha final del periodo (Y-m-d).
     */
    public static function get_comprobantes_pendientes(string $fecha_inicio, string $fecha_fin): array
    {
        $sql = self::sql_comprobantes();

        $rows = DB::select($sql, [
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
            'estado_pendiente' => EstadoComprobanteDespacho::PendienteDePago->value,
            'estado_en_proceso' => EstadoComprobanteDespacho::EnProcesoPago->value,
        ]);

        return array_map([self::class, 'normalizar'], $rows);
    }

    /**
     * Anticipos de cliente con saldo por amortizar.
     *
     * Es dinero ya recibido que todavia no se facturo: cruza con la cartera
     * para saber cuanto de lo "por cobrar" ya esta cubierto.
     */
    public static function get_anticipos_con_saldo(): array
    {
        $sql = '
        SELECT
            a.id AS id_anticipo,
            a.id_cliente,
            cli.razon_social AS cliente,
            a.fecha_hora_pago,
            a.saldo_actual,
            a.estado
        FROM anticipo_cliente a
        LEFT JOIN cliente cli ON cli.id = a.id_cliente
        WHERE a.estado = :estado_con_saldo
          AND COALESCE(a.saldo_actual, 0) > 0
        ORDER BY a.saldo_actual DESC
        ';

        $rows = DB::select($sql, [
            'estado_con_saldo' => EstadoAnticipo::ConSaldo->value,
        ]);

        return array_map(fn (object $row) => [
            'id_anticipo' => (int) $row->id_anticipo,
            'id_cliente' => $row->id_cliente !== null ? (int) $row->id_cliente : null,
            'cliente' => $row->cliente !== null ? (string) $row->cliente : null,
            'fecha_hora_pago' => (string) $row->fecha_hora_pago,
            'saldo_actual' => round((float) $row->saldo_actual, 2),
            'estado' => (string) $row->estado,
        ], $rows);
    }

    /**
     * UNION de los cinco tipos de comprobante con la misma forma de fila.
     *
     * El periodo y los estados se inyectan una sola vez por CTE para que los
     * parametros named no se repitan en cada rama.
     */
    private static function sql_comprobantes(): string
    {
        return '
        WITH periodo AS (
            SELECT
                CAST(:fecha_inicio AS DATE) AS f_ini,
                DATE_ADD(CAST(:fecha_fin AS DATE), INTERVAL 1 DAY) AS f_fin_excl,
                CAST(:estado_pendiente AS CHAR(32)) AS e_pendiente,
                CAST(:estado_en_proceso AS CHAR(32)) AS e_en_proceso
        )

        -- 1) MINERAL - Liquidacion del despacho (siempre en dolares)
        SELECT
            CAST("LIQUIDACION" AS CHAR(16))          AS tipo,
            cl.id                                     AS id_comprobante,
            cl.codigo_comprobante                     AS codigo,
            li.id_cliente                             AS id_cliente,
            cli.razon_social                         AS cliente,
            cl.fecha_emision                          AS fecha_emision,
            CAST("DÃ³lares" AS CHAR(16))               AS moneda,
            COALESCE(cl.total, 0)                    AS monto_total,
            COALESCE(cl.neto_a_pagar, 0)              AS monto_neto,
            COALESCE(cl.avance_neto, 0)
              + COALESCE(cl.avance_detraccion, 0)    AS monto_pagado,
            cl.estado                                 AS estado,
            CAST("MINERAL" AS CHAR(16))               AS ambito
        FROM comprobante_liquidacion_despacho cl
        CROSS JOIN periodo p
        LEFT JOIN liquidacion_mineral li ON li.id = cl.id_liquidacion_mineral
        LEFT JOIN cliente cli ON cli.id = li.id_cliente
        WHERE cl.fecha_emision >= p.f_ini
          AND cl.fecha_emision <  p.f_fin_excl
          AND CAST(cl.estado AS CHAR) IN (p.e_pendiente, p.e_en_proceso)

        UNION ALL

        -- 2) MINERAL - Flete del despacho (conserva su moneda original)
        SELECT
            CAST("FLETE" AS CHAR(16))                AS tipo,
            cf.id                                     AS id_comprobante,
            cf.codigo_comprobante                     AS codigo,
            li.id_cliente                             AS id_cliente,
            cli.razon_social                         AS cliente,
            cf.fecha_emision                          AS fecha_emision,
            CAST(COALESCE(cf.moneda, "Soles") AS CHAR(16)) AS moneda,
            COALESCE(cf.total, 0)                    AS monto_total,
            COALESCE(cf.neto_a_pagar, 0)              AS monto_neto,
            COALESCE(cf.avance_neto, 0)
              + COALESCE(cf.avance_detraccion, 0)    AS monto_pagado,
            cf.estado                                 AS estado,
            CAST("MINERAL" AS CHAR(16))               AS ambito
        FROM comprobante_flete_despacho cf
        CROSS JOIN periodo p
        LEFT JOIN despacho_mineral d ON d.id = cf.id_despacho_mineral
        LEFT JOIN liquidacion_mineral li ON li.id_despacho_mineral = d.id
        LEFT JOIN cliente cli ON cli.id = li.id_cliente
        WHERE cf.fecha_emision >= p.f_ini
          AND cf.fecha_emision <  p.f_fin_excl
          AND CAST(cf.estado AS CHAR) IN (p.e_pendiente, p.e_en_proceso)

        UNION ALL

        -- 3) MINERAL - Molienda (siempre en soles)
        SELECT
            CAST("MOLIENDA" AS CHAR(16))              AS tipo,
            cm.id                                     AS id_comprobante,
            cm.codigo_comprobante                     AS codigo,
            li.id_cliente                             AS id_cliente,
            cli.razon_social                         AS cliente,
            cm.fecha_emision                          AS fecha_emision,
            CAST("Soles" AS CHAR(16))                 AS moneda,
            COALESCE(cm.total, 0)                    AS monto_total,
            COALESCE(cm.neto_a_pagar, 0)              AS monto_neto,
            COALESCE(cm.avance_neto, 0)
              + COALESCE(cm.avance_detraccion, 0)    AS monto_pagado,
            cm.estado                                 AS estado,
            CAST("MINERAL" AS CHAR(16))               AS ambito
        FROM comprobante_molienda_despacho cm
        CROSS JOIN periodo p
        LEFT JOIN despacho_mineral d ON d.id = cm.id_despacho_mineral
        LEFT JOIN liquidacion_mineral li ON li.id_despacho_mineral = d.id
        LEFT JOIN cliente cli ON cli.id = li.id_cliente
        WHERE cm.fecha_emision >= p.f_ini
          AND cm.fecha_emision <  p.f_fin_excl
          AND CAST(cm.estado AS CHAR) IN (p.e_pendiente, p.e_en_proceso)

        UNION ALL

        -- 4) MINERAL - Analisis de laboratorio (siempre en soles)
        SELECT
            CAST("ANALISIS" AS CHAR(16))              AS tipo,
            ca.id                                     AS id_comprobante,
            ca.codigo_comprobante                     AS codigo,
            li.id_cliente                             AS id_cliente,
            cli.razon_social                         AS cliente,
            ca.fecha_emision                          AS fecha_emision,
            CAST("Soles" AS CHAR(16))                 AS moneda,
            COALESCE(ca.total, 0)                    AS monto_total,
            COALESCE(ca.neto_a_pagar, 0)              AS monto_neto,
            COALESCE(ca.avance_neto, 0)
              + COALESCE(ca.avance_detraccion, 0)    AS monto_pagado,
            ca.estado                                 AS estado,
            CAST("MINERAL" AS CHAR(16))               AS ambito
        FROM comprobante_analisis_despacho ca
        CROSS JOIN periodo p
        LEFT JOIN despacho_mineral d ON d.id = ca.id_despacho_mineral
        LEFT JOIN liquidacion_mineral li ON li.id_despacho_mineral = d.id
        LEFT JOIN cliente cli ON cli.id = li.id_cliente
        WHERE ca.fecha_emision >= p.f_ini
          AND ca.fecha_emision <  p.f_fin_excl
          AND CAST(ca.estado AS CHAR) IN (p.e_pendiente, p.e_en_proceso)

        UNION ALL

        -- 5) CARBON - Venta de carbon (los anticipos aplicados son pago)
        SELECT
            CAST("VENTA_CARBON" AS CHAR(16))          AS tipo,
            cvc.id                                    AS id_comprobante,
            cvc.codigo_comprobante                    AS codigo,
            vc.id_cliente                             AS id_cliente,
            cli.razon_social                         AS cliente,
            cvc.fecha_emision                         AS fecha_emision,
            CAST("Soles" AS CHAR(16))                 AS moneda,
            COALESCE(cvc.importe_total, 0)            AS monto_total,
            COALESCE(cvc.monto_neto, cvc.importe_total, 0) AS monto_neto,
            COALESCE(cvc.avance_pago_neto, 0)
              + COALESCE(cvc.monto_pagado_anticipos, 0) AS monto_pagado,
            cvc.estado                                AS estado,
            CAST("CARBON" AS CHAR(16))                AS ambito
        FROM comprobante_venta_carbon cvc
        CROSS JOIN periodo p
        LEFT JOIN venta_carbon vc ON vc.id = cvc.id_venta_carbon
        LEFT JOIN cliente cli ON cli.id = vc.id_cliente
        WHERE cvc.fecha_emision >= p.f_ini
          AND cvc.fecha_emision <  p.f_fin_excl
          AND CAST(cvc.estado AS CHAR) IN (p.e_pendiente, p.e_en_proceso)
        ';
    }

    /**
     * Normaliza una fila de comprobante al contrato del front.
     */
    private static function normalizar(object $row): array
    {
        $neto = (float) $row->monto_neto;
        $pagado = (float) $row->monto_pagado;

        return [
            'tipo' => (string) $row->tipo,
            'id_comprobante' => (int) $row->id_comprobante,
            'codigo' => $row->codigo !== null ? (string) $row->codigo : null,
            'id_cliente' => $row->id_cliente !== null ? (int) $row->id_cliente : null,
            'cliente' => $row->cliente !== null ? (string) $row->cliente : null,
            'fecha_emision' => (string) $row->fecha_emision,
            'moneda' => (string) $row->moneda,
            'ambito' => (string) $row->ambito,
            'estado' => (string) $row->estado,
            'monto_total' => round((float) $row->monto_total, 2),
            'monto_neto' => round($neto, 2),
            'monto_pagado' => round($pagado, 2),
            // El saldo nunca es negativo: un sobrepago no es saldo a favor del
            // cliente en este indicador, se resuelve en el modulo de origen.
            'saldo' => round(max(0, $neto - $pagado), 2),
            'avance_pct' => $neto > 0 ? round(min(100, ($pagado / $neto) * 100), 1) : 0,
        ];
    }
}
