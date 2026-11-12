<?php

namespace App\Modules\VentaMineral\Data;

use App\Models\DespachoMineral;
use App\Shared\Enums\LoteMineral\EstadoLoteMineral;
use App\Shared\Enums\VentaMineral\EstadoDespachoMineral;
use Illuminate\Support\Facades\DB;

/**
 * Data de los despachos de mineral a molienda.
 *
 * Es el unico lugar con SQL de los despachos.
 */
class VentaMineralData
{
    /**
     * Columnas de la consulta de despachos.
     *
     * El listado y el detalle usan EXACTAMENTE las mismas columnas: el front
     * pinta los mismos indicadores en la tarjeta y en el panel de detalle, y
     * no puede ser que uno los traiga y el otro no.
     */
    private static function select_despachos(): string
    {
        return '
        SELECT
            d.id AS id_despacho_mineral,
            d.id_lote_mineral,
            d.id_molino,
            d.id_empleado_registro,
            d.fecha_despacho,
            d.cantidad_sacos,
            d.observaciones,
            d.estado,
            d.created_at,

            l.codigo AS lote_codigo,
            l.cantidad_sacos AS lote_cantidad_sacos,
            l.id_labor,
            labor.nombre AS labor,
            labor.prefijo AS labor_prefijo,
            mina.nombre AS mina,
            CONCAT(ctr.nombre, " ", COALESCE(ctr.apellido, "")) AS contratista,

            mol.razon_social AS molino,

            CONCAT(emp.nombre, " ", COALESCE(emp.apellido, "")) AS empleado_registro,

            COALESCE(fl.cantidad_fletes, 0) AS cantidad_fletes,
            COALESCE(fl.peso_neto_total, 0) AS peso_neto_total,
            COALESCE(fl.peso_guia_total, 0) AS peso_guia_total,

            COALESCE(gs.total_gastos, 0) AS total_gastos,

            COALESCE(cf.total_facturado, 0) AS total_facturado,
            COALESCE(cf.total_neto, 0) AS total_neto,
            COALESCE(cf.total_pagado, 0) AS total_pagado,
            COALESCE(cf.total_detraccion, 0) AS total_detraccion,
            COALESCE(cf.total_facturado_dolares, 0) AS total_facturado_dolares,
            COALESCE(cf.total_neto_dolares, 0) AS total_neto_dolares,
            COALESCE(cf.total_pagado_dolares, 0) AS total_pagado_dolares,

            COALESCE(cm.total_facturado, 0) AS total_facturado_molienda,
            COALESCE(cm.total_neto, 0) AS total_neto_molienda,
            COALESCE(cm.total_pagado, 0) AS total_pagado_molienda,

            COALESCE(ca.total_facturado, 0) AS total_facturado_analisis,
            COALESCE(ca.total_neto, 0) AS total_neto_analisis,
            COALESCE(ca.total_pagado, 0) AS total_pagado_analisis,

            COALESCE(cl.total_facturado, 0) AS total_facturado_liquidacion,
            COALESCE(cl.total_neto, 0) AS total_neto_liquidacion,
            COALESCE(cl.total_pagado, 0) AS total_pagado_liquidacion
        FROM despacho_mineral d
        LEFT JOIN lote_mineral l ON l.id = d.id_lote_mineral
        LEFT JOIN labor labor ON labor.id = l.id_labor
        LEFT JOIN mina mina ON mina.id = l.id_mina
        LEFT JOIN empleado ctr ON ctr.id = l.id_contratista
        LEFT JOIN molino mol ON mol.id = d.id_molino
        LEFT JOIN empleado emp ON emp.id = d.id_empleado_registro
        LEFT JOIN (
            SELECT id_despacho_mineral,
                   COUNT(*) AS cantidad_fletes,
                   COALESCE(SUM(peso_neto), 0) AS peso_neto_total,
                   COALESCE(SUM(peso_guia), 0) AS peso_guia_total
            FROM flete_despacho
            GROUP BY id_despacho_mineral
        ) fl ON fl.id_despacho_mineral = d.id
        LEFT JOIN (
            SELECT id_despacho_mineral, COALESCE(SUM(monto), 0) AS total_gastos
            FROM gasto_despacho
            GROUP BY id_despacho_mineral
        ) gs ON gs.id_despacho_mineral = d.id
        LEFT JOIN (
            SELECT id_despacho_mineral,
                   -- Los importes se separan por MONEDA: los de soles (que es
                   -- la moneda de referencia) y los de dolares. Un
                   -- comprobante en dolares nunca se suma en soles.
                   COALESCE(SUM(CASE WHEN COALESCE(moneda, \'Soles\') = \'Soles\' THEN total ELSE 0 END), 0) AS total_facturado,
                   COALESCE(SUM(CASE WHEN COALESCE(moneda, \'Soles\') = \'Soles\' THEN neto_a_pagar ELSE 0 END), 0) AS total_neto,
                   -- El avance de la detraccion SIEMPRE esta en soles.
                   COALESCE(SUM(CASE WHEN COALESCE(moneda, \'Soles\') = \'Soles\' THEN avance_neto ELSE 0 END), 0)
                     + COALESCE(SUM(avance_detraccion), 0) AS total_pagado,
                   COALESCE(SUM(monto_detraccion_soles), 0) AS total_detraccion,
                   COALESCE(SUM(CASE WHEN moneda = \'Dólares\' THEN total ELSE 0 END), 0) AS total_facturado_dolares,
                   COALESCE(SUM(CASE WHEN moneda = \'Dólares\' THEN neto_a_pagar ELSE 0 END), 0) AS total_neto_dolares,
                   COALESCE(SUM(CASE WHEN moneda = \'Dólares\' THEN avance_neto ELSE 0 END), 0) AS total_pagado_dolares
            FROM comprobante_flete_despacho
            GROUP BY id_despacho_mineral
        ) cf ON cf.id_despacho_mineral = d.id
        LEFT JOIN (
            SELECT id_despacho_mineral,
                   COALESCE(SUM(total), 0) AS total_facturado,
                   COALESCE(SUM(neto_a_pagar), 0) AS total_neto,
                   COALESCE(SUM(avance_neto + avance_detraccion), 0) AS total_pagado
            FROM comprobante_molienda_despacho
            GROUP BY id_despacho_mineral
        ) cm ON cm.id_despacho_mineral = d.id
        LEFT JOIN (
            SELECT id_despacho_mineral,
                   COALESCE(SUM(total), 0) AS total_facturado,
                   COALESCE(SUM(neto_a_pagar), 0) AS total_neto,
                   COALESCE(SUM(avance_neto + avance_detraccion), 0) AS total_pagado
            FROM comprobante_analisis_despacho
            GROUP BY id_despacho_mineral
        ) ca ON ca.id_despacho_mineral = d.id
        LEFT JOIN (
            SELECT id_despacho_mineral,
                   COALESCE(SUM(total), 0) AS total_facturado,
                   COALESCE(SUM(neto_a_pagar), 0) AS total_neto,
                   COALESCE(SUM(avance_neto + avance_detraccion), 0) AS total_pagado
            FROM comprobante_liquidacion_despacho
            GROUP BY id_despacho_mineral
        ) cl ON cl.id_despacho_mineral = d.id
        ';
    }

    /**
     * Normaliza los tipos de una fila de despacho.
     */
    private static function normalizar_despacho(object $row): object
    {
        $row->id_despacho_mineral = (int) $row->id_despacho_mineral;
        $row->id_lote_mineral = $row->id_lote_mineral !== null ? (int) $row->id_lote_mineral : null;
        $row->id_molino = $row->id_molino !== null ? (int) $row->id_molino : null;
        $row->cantidad_sacos = (float) $row->cantidad_sacos;
        $row->cantidad_fletes = (int) $row->cantidad_fletes;

        return $row;
    }

    /**
     * Lista los despachos con su resumen financiero.
     */
    public static function get_despachos(
        ?int $mes = null,
        ?int $anio = null,
        ?string $estado = null,
        ?int $id_lote_mineral = null
    ): array {
        $sql = self::select_despachos() . ' WHERE 1=1 ';

        $params = [];

        if ($mes !== null && $anio !== null) {
            $sql .= ' AND MONTH(d.fecha_despacho) = :mes';
            $sql .= ' AND YEAR(d.fecha_despacho) = :anio';
            $params['mes'] = $mes;
            $params['anio'] = $anio;
        }

        if ($estado !== null && $estado !== '') {
            $sql .= ' AND d.estado = :estado';
            $params['estado'] = $estado;
        }

        if ($id_lote_mineral !== null) {
            $sql .= ' AND d.id_lote_mineral = :id_lote_mineral';
            $params['id_lote_mineral'] = $id_lote_mineral;
        }

        $sql .= ' ORDER BY d.fecha_despacho DESC, d.id DESC';

        $rows = DB::select($sql, $params);

        return array_map(fn ($row) => self::normalizar_despacho($row), $rows);
    }

    /**
     * Un despacho con sus datos de cabecera y su resumen financiero.
     */
    public static function get_despacho(int $id_despacho_mineral): ?object
    {
        $sql = self::select_despachos() . ' WHERE d.id = :id LIMIT 1';

        $row = DB::selectOne($sql, ['id' => $id_despacho_mineral]);

        if ($row === null) {
            return null;
        }

        return self::normalizar_despacho($row);
    }

    /**
     * Lotes finalizados todavia sin despacho: son los despachables.
     */
    public static function get_lotes_despachables(): array
    {
        $sql = '
        SELECT
            l.id AS id_lote_mineral,
            l.codigo,
            l.cantidad_sacos,
            l.fecha_inicio_produccion,
            l.id_labor,
            labor.nombre AS labor,
            labor.prefijo AS labor_prefijo,
            mina.nombre AS mina,
            CONCAT(ctr.nombre, " ", COALESCE(ctr.apellido, "")) AS contratista,
            l.created_at
        FROM lote_mineral l
        INNER JOIN mina mina ON mina.id = l.id_mina
        INNER JOIN empleado ctr ON ctr.id = l.id_contratista
        LEFT JOIN labor labor ON labor.id = l.id_labor
        WHERE l.estado = :estado
          AND NOT EXISTS (
              SELECT 1 FROM despacho_mineral d WHERE d.id_lote_mineral = l.id
          )
        ORDER BY l.fecha_inicio_produccion DESC, l.id DESC
        ';

        $rows = DB::select($sql, ['estado' => EstadoLoteMineral::Finalizado->value]);

        foreach ($rows as $row) {
            $row->id_lote_mineral = (int) $row->id_lote_mineral;
            $row->cantidad_sacos = (float) $row->cantidad_sacos;
        }

        return $rows;
    }

    /**
     * Un lote de mineral.
     */
    public static function get_lote(int $id_lote_mineral): ?object
    {
        $sql = '
        SELECT
            l.id AS id_lote_mineral,
            l.codigo,
            l.cantidad_sacos,
            l.fecha_inicio_produccion,
            l.estado,
            l.id_labor,
            labor.nombre AS labor,
            labor.prefijo AS labor_prefijo,
            mina.nombre AS mina,
            CONCAT(ctr.nombre, " ", COALESCE(ctr.apellido, "")) AS contratista
        FROM lote_mineral l
        INNER JOIN mina mina ON mina.id = l.id_mina
        INNER JOIN empleado ctr ON ctr.id = l.id_contratista
        LEFT JOIN labor labor ON labor.id = l.id_labor
        WHERE l.id = :id
        ';

        $row = DB::selectOne($sql, ['id' => $id_lote_mineral]);

        if ($row === null) {
            return null;
        }

        $row->id_lote_mineral = (int) $row->id_lote_mineral;
        $row->cantidad_sacos = (float) $row->cantidad_sacos;

        return $row;
    }

    /**
     * Estado de despachabilidad de un lote.
     *
     * PUNTO DE EXTENSION (Opcion B): hoy un lote se despacha UNA SOLA VEZ, asi
     * que en cuanto tiene un despacho no queda nada disponible. Para permitir
     * despachos parciales, aqui basta con devolver la cantidad de sacos del
     * lote menos la suma de los ya despachados: ni las tablas, ni los DTOs, ni
     * el front cambian.
     *
     * @return array{
     *   lote: object,
     *   despachados: float,
     *   disponibles: float,
     *   despacho_existente: object|null
     * }
     */
    public static function sacos_disponibles_lote(int $id_lote_mineral): array
    {
        $lote = self::get_lote($id_lote_mineral);

        if ($lote === null) {
            return [
                'lote' => null,
                'despachados' => 0.0,
                'disponibles' => 0.0,
                'despacho_existente' => null,
            ];
        }

        $despachoExistente = DB::table('despacho_mineral')
            ->where('id_lote_mineral', $id_lote_mineral)
            ->orderBy('id', 'asc')
            ->first();

        $despachados = (float) DB::table('despacho_mineral')
            ->where('id_lote_mineral', $id_lote_mineral)
            ->sum('cantidad_sacos');

        // Opcion A: un lote se despacha una sola vez.
        $disponibles = $despachoExistente !== null
            ? 0.0
            : (float) $lote->cantidad_sacos;

        return [
            'lote' => $lote,
            'despachados' => $despachados,
            'disponibles' => $disponibles,
            'despacho_existente' => $despachoExistente,
        ];
    }

    /**
     * Registra un despacho de mineral.
     *
     * @param array<string, mixed> $d
     */
    public static function registrar_despacho(array $d): int
    {
        return DespachoMineral::insertGetId([
            'id_lote_mineral' => (int) $d['id_lote_mineral'],
            'id_molino' => (int) $d['id_molino'],
            'id_empleado_registro' => (int) $d['id_empleado_registro'],
            'fecha_despacho' => (string) $d['fecha_despacho'],
            'cantidad_sacos' => (float) $d['cantidad_sacos'],
            'observaciones' => isset($d['observaciones']) && $d['observaciones'] !== ''
                ? (string) $d['observaciones']
                : null,
            'created_at' => now()->toDateTimeString(),
            'estado' => EstadoDespachoMineral::Registrado->value,
        ]);
    }

    /**
     * Actualiza la cabecera de un despacho y recalcula su estado.
     *
     * @param array<string, mixed> $d
     */
    public static function actualizar_despacho(array $d): void
    {
        DB::transaction(function () use ($d) {
            $id = (int) $d['id_despacho_mineral'];

            $actualizaciones = [
                'id_molino' => (int) $d['id_molino'],
                'fecha_despacho' => (string) $d['fecha_despacho'],
                'cantidad_sacos' => (float) $d['cantidad_sacos'],
            ];

            if (array_key_exists('observaciones', $d)) {
                $actualizaciones['observaciones'] = isset($d['observaciones']) && $d['observaciones'] !== ''
                    ? (string) $d['observaciones']
                    : null;
            }

            DB::table('despacho_mineral')
                ->where('id', $id)
                ->update($actualizaciones);

            self::recalcular_estado_despacho($id);
        });
    }

    /**
     * Elimina un despacho y todo su arbol (gastos, fletes, comprobantes y
     * pagos). El Service bloquea el caso en que ya haya pagos registrados.
     */
    public static function eliminar_despacho(int $id_despacho_mineral): void
    {
        DB::transaction(function () use ($id_despacho_mineral) {
            $idsComprobantesFlete = DB::table('comprobante_flete_despacho')
                ->where('id_despacho_mineral', $id_despacho_mineral)
                ->pluck('id')->all();
            $idsComprobantesMolienda = DB::table('comprobante_molienda_despacho')
                ->where('id_despacho_mineral', $id_despacho_mineral)
                ->pluck('id')->all();
            $idsComprobantesAnalisis = DB::table('comprobante_analisis_despacho')
                ->where('id_despacho_mineral', $id_despacho_mineral)
                ->pluck('id')->all();
            $idsComprobantesLiquidacion = DB::table('comprobante_liquidacion_despacho')
                ->where('id_despacho_mineral', $id_despacho_mineral)
                ->pluck('id')->all();
            DB::table('pago_comprobante_flete_despacho')
                ->whereIn('id_comprobante_flete_despacho', $idsComprobantesFlete)
                ->delete();
            DB::table('pago_comprobante_molienda_despacho')
                ->whereIn('id_comprobante_molienda_despacho', $idsComprobantesMolienda)
                ->delete();
            DB::table('pago_comprobante_analisis_despacho')
                ->whereIn('id_comprobante_analisis_despacho', $idsComprobantesAnalisis)
                ->delete();
            DB::table('pago_comprobante_liquidacion_despacho')
                ->whereIn('id_comprobante_liquidacion_despacho', $idsComprobantesLiquidacion)
                ->delete();

            DB::table('comprobante_flete_despacho')
                ->whereIn('id', $idsComprobantesFlete)->delete();
            DB::table('comprobante_molienda_despacho')
                ->whereIn('id', $idsComprobantesMolienda)->delete();
            DB::table('comprobante_analisis_despacho')
                ->whereIn('id', $idsComprobantesAnalisis)->delete();
            DB::table('comprobante_liquidacion_despacho')
                ->whereIn('id', $idsComprobantesLiquidacion)->delete();

            DB::table('analisis_laboratorio')
                ->where('id_despacho_mineral', $id_despacho_mineral)
                ->delete();
            DB::table('liquidacion_mineral')
                ->where('id_despacho_mineral', $id_despacho_mineral)
                ->delete();
            DB::table('flete_despacho')
                ->where('id_despacho_mineral', $id_despacho_mineral)
                ->delete();
            DB::table('gasto_despacho')
                ->where('id_despacho_mineral', $id_despacho_mineral)
                ->delete();
            DB::table('despacho_mineral')
                ->where('id', $id_despacho_mineral)
                ->delete();
        });
    }

    /**
     * Recalcula el estado persistido del despacho.
     *
     * - Registrado: todavia no tiene fletes.
     * - En Proceso: tiene fletes y no tiene liquidacion.
     * - Liquidado: tiene al menos una liquidacion.
     */
    public static function recalcular_estado_despacho(int $id_despacho_mineral): void
    {
        $cantidadFletes = (int) DB::table('flete_despacho')
            ->where('id_despacho_mineral', $id_despacho_mineral)
            ->count();

        $cantidadLiquidaciones = (int) DB::table('liquidacion_mineral')
            ->where('id_despacho_mineral', $id_despacho_mineral)
            ->count();

        $estado = EstadoDespachoMineral::Registrado;
        if ($cantidadLiquidaciones > 0) {
            $estado = EstadoDespachoMineral::Liquidado;
        } elseif ($cantidadFletes > 0) {
            $estado = EstadoDespachoMineral::EnProceso;
        }

        DB::table('despacho_mineral')
            ->where('id', $id_despacho_mineral)
            ->update(['estado' => $estado->value]);
    }

    /**
     * Serializa las evidencias a JSON para la columna.
     */
    public static function encode_json(mixed $valor): ?string
    {
        if ($valor === null || $valor === '' || $valor === []) {
            return null;
        }

        if (is_string($valor)) {
            return $valor;
        }

        return json_encode($valor, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Lee las evidencias desde la columna JSON.
     */
    public static function decode_json(mixed $valor): mixed
    {
        if (is_array($valor) || is_object($valor)) {
            return $valor;
        }

        if (is_string($valor) && $valor !== '') {
            $dec = json_decode($valor, true);
            return json_last_error() === JSON_ERROR_NONE ? $dec : [];
        }

        return [];
    }
}
