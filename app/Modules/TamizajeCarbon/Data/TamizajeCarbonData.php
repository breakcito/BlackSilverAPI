<?php

namespace App\Modules\TamizajeCarbon\Data;

use App\Models\StockCarbon;
use App\Models\TamizajeCarbon;
use App\Models\VarianteTamizajeCarbon;
use Illuminate\Support\Facades\DB;

class TamizajeCarbonData
{
    /**
     * Lista el stock de carbón por almacén y tipo de carbón.
     * @param array{id_almacen?: int, id_tipo_carbon?: int, filtros?: string} $opts
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
                s.stock_actual,
                s.cambios_log
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

        $filtros = trim((string) ($opts['filtros'] ?? ''));
        if ($filtros !== '') {
            $sql .= ' AND (tc.nombre LIKE :q OR tc.codigo LIKE :q OR alm.nombre LIKE :q)';
            $params['q'] = '%' . $filtros . '%';
        }

        $sql .= ' ORDER BY alm.nombre ASC, tc.nombre ASC';

        $rows = DB::select($sql, $params);
        foreach ($rows as $row) {
            $row->cambios_log = self::decode_json($row->cambios_log ?? null);
        }

        return $rows;
    }

    /**
     * Actualiza manualmente el peso/stock y guarda en cambios_log.
     * @param array<string, mixed> $logEntry
     */
    public static function actualizar_stock_manual(int $id_stock_carbon, float $nuevo_stock, array $logEntry): void
    {
        $stock = DB::table('stock_carbon')->where('id', $id_stock_carbon)->first();
        if ($stock === null) {
            throw new \RuntimeException('Stock no encontrado');
        }

        $historial = self::decode_json($stock->cambios_log ?? null);
        $historial[] = $logEntry;

        DB::table('stock_carbon')
            ->where('id', $id_stock_carbon)
            ->update([
                'stock_actual' => $nuevo_stock,
                'cambios_log' => json_encode($historial, JSON_UNESCAPED_UNICODE),
            ]);
    }

    /**
     * Lista los tamizajes realizados con sus variantes.
     * @param array{id_almacen?: int, mes?: int, anio?: int, filtros?: string} $opts
     * @return array<object>
     */
    public static function get_tamizajes(array $opts = []): array
    {
        $sql = '
            SELECT
                tz.id AS id_tamizaje_carbon,
                tz.id_almacen,
                alm.nombre AS almacen_nombre,
                tz.id_empleado_registro,
                CONCAT(er.nombre, " ", er.apellido) AS empleado_registro,
                tz.id_empleado_supervisor,
                CONCAT(es.nombre, " ", es.apellido) AS empleado_supervisor,
                tz.id_tipo_carbon,
                tc.nombre AS tipo_carbon_nombre,
                tc.codigo AS tipo_carbon_codigo,
                tz.id_carga_compra_carbon,
                c.codigo_ticket_balanza AS carga_ticket_balanza,
                c.placa AS carga_placa,
                cc.correlativo AS compra_correlativo,
                tz.cantidad_tamizada,
                tz.cantidad_extraida,
                tz.es_retamizaje,
                tz.fecha_hora_tamizaje,
                tz.evidencias,
                tz.created_at
            FROM tamizaje_carbon tz
            INNER JOIN almacen alm ON alm.id = tz.id_almacen
            INNER JOIN empleado er ON er.id = tz.id_empleado_registro
            INNER JOIN tipo_carbon tc ON tc.id = tz.id_tipo_carbon
            LEFT JOIN empleado es ON es.id = tz.id_empleado_supervisor
            LEFT JOIN carga_compra_carbon c ON c.id = tz.id_carga_compra_carbon
            LEFT JOIN compra_carbon cc ON cc.id = c.id_compra_carbon
            WHERE 1 = 1
        ';

        $params = [];

        if (!empty($opts['id_almacen'])) {
            $sql .= ' AND tz.id_almacen = :id_almacen';
            $params['id_almacen'] = (int) $opts['id_almacen'];
        }

        $mes = isset($opts['mes']) ? (int) $opts['mes'] : 0;
        $anio = isset($opts['anio']) ? (int) $opts['anio'] : 0;
        if ($mes > 0 && $anio > 0) {
            $sql .= ' AND MONTH(tz.fecha_hora_tamizaje) = :mes AND YEAR(tz.fecha_hora_tamizaje) = :anio';
            $params['mes'] = $mes;
            $params['anio'] = $anio;
        } elseif ($anio > 0) {
            $sql .= ' AND YEAR(tz.fecha_hora_tamizaje) = :anio';
            $params['anio'] = $anio;
        }

        $filtros = trim((string) ($opts['filtros'] ?? ''));
        if ($filtros !== '') {
            $sql .= ' AND (tc.nombre LIKE :q OR alm.nombre LIKE :q OR c.codigo_ticket_balanza LIKE :q OR c.placa LIKE :q)';
            $params['q'] = '%' . $filtros . '%';
        }

        $sql .= ' ORDER BY tz.fecha_hora_tamizaje DESC, tz.id DESC';

        $tamizajes = DB::select($sql, $params);
        foreach ($tamizajes as $tz) {
            $tz->evidencias = self::decode_json($tz->evidencias ?? null);

            // Variantes extraídas
            $tz->variantes = DB::select('
                SELECT
                    vt.id AS id_variante_tamizaje_carbon,
                    vt.id_tamizaje_carbon,
                    vt.id_tipo_variante,
                    tcv.nombre AS tipo_variante_nombre,
                    tcv.codigo AS tipo_variante_codigo,
                    vt.cantidad_extraida,
                    vt.cambios_log
                FROM variante_tamizaje_carbon vt
                INNER JOIN tipo_carbon tcv ON tcv.id = vt.id_tipo_variante
                WHERE vt.id_tamizaje_carbon = :id_tz
                ORDER BY vt.id ASC
            ', ['id_tz' => $tz->id_tamizaje_carbon]);

            foreach ($tz->variantes as $v) {
                $v->cambios_log = self::decode_json($v->cambios_log ?? null);
            }
        }

        return $tamizajes;
    }

    /**
     * Registra un tamizaje de carbón y actualiza los stocks correspondientes.
     * @param array{
     *   id_almacen: int,
     *   id_empleado_registro: int,
     *   id_empleado_supervisor?: int|null,
     *   id_tipo_carbon: int,
     *   id_carga_compra_carbon?: int|null,
     *   cantidad_tamizada: float,
     *   es_retamizaje: bool,
     *   fecha_hora_tamizaje: string,
     *   evidencias?: mixed,
     *   variantes: array<int, array{id_tipo_variante: int, cantidad_extraida: float}>
     * } $d
     */
    public static function registrar_tamizaje(array $d): int
    {
        return DB::transaction(function () use ($d) {
            $idAlmacen = (int) $d['id_almacen'];
            $idTipoCarbon = (int) $d['id_tipo_carbon'];
            $variantes = $d['variantes'];

            // Cantidad extraída total = suma de todas las variantes
            $cantidadExtraidaTotal = 0.0;
            foreach ($variantes as $v) {
                $cantidadExtraidaTotal += round((float) $v['cantidad_extraida'], 4);
            }

            $evidenciasJson = isset($d['evidencias']) && $d['evidencias'] !== null
                ? (is_string($d['evidencias']) ? $d['evidencias'] : json_encode($d['evidencias'], JSON_UNESCAPED_UNICODE))
                : null;

            $idTamizaje = TamizajeCarbon::insertGetId([
                'id_almacen' => $idAlmacen,
                'id_empleado_registro' => (int) $d['id_empleado_registro'],
                'id_empleado_supervisor' => isset($d['id_empleado_supervisor']) && (int) $d['id_empleado_supervisor'] > 0
                    ? (int) $d['id_empleado_supervisor']
                    : null,
                'id_tipo_carbon' => $idTipoCarbon,
                'id_carga_compra_carbon' => isset($d['id_carga_compra_carbon']) && (int) $d['id_carga_compra_carbon'] > 0
                    ? (int) $d['id_carga_compra_carbon']
                    : null,
                'cantidad_tamizada' => (float) $d['cantidad_tamizada'],
                'cantidad_extraida' => $cantidadExtraidaTotal,
                'es_retamizaje' => !empty($d['es_retamizaje']) ? 1 : 0,
                'fecha_hora_tamizaje' => (string) $d['fecha_hora_tamizaje'],
                'evidencias' => $evidenciasJson,
                'created_at' => now()->toDateTimeString(),
            ]);

            // Insertar variantes y actualizar stock de cada una
            foreach ($variantes as $v) {
                $idTipoVariante = (int) $v['id_tipo_variante'];
                $cantVar = round((float) $v['cantidad_extraida'], 4);

                VarianteTamizajeCarbon::insert([
                    'id_tamizaje_carbon' => $idTamizaje,
                    'id_tipo_variante' => $idTipoVariante,
                    'cantidad_extraida' => $cantVar,
                    'cambios_log' => null,
                ]);

                // Sumar al stock de la variante en ese almacén
                self::ajustar_stock_almacen($idAlmacen, $idTipoVariante, $cantVar);
            }

            // Restar del stock del tipo de carbón tamizado
            self::ajustar_stock_almacen($idAlmacen, $idTipoCarbon, -$cantidadExtraidaTotal);

            return $idTamizaje;
        });
    }

    /**
     * Ajusta el stock de un tipo de carbón en un almacén (suma o resta).
     */
    private static function ajustar_stock_almacen(int $idAlmacen, int $idTipoCarbon, float $delta): void
    {
        $stock = DB::table('stock_carbon')
            ->where('id_almacen', $idAlmacen)
            ->where('id_tipo_carbon', $idTipoCarbon)
            ->lockForUpdate()
            ->first();

        if ($stock === null) {
            $nuevoStock = max(0.0, round($delta, 4));
            StockCarbon::insert([
                'id_almacen' => $idAlmacen,
                'id_tipo_carbon' => $idTipoCarbon,
                'stock_actual' => $nuevoStock,
                'cambios_log' => null,
            ]);
        } else {
            $nuevoStock = max(0.0, round(((float) $stock->stock_actual) + $delta, 4));
            DB::table('stock_carbon')
                ->where('id', $stock->id)
                ->update(['stock_actual' => $nuevoStock]);
        }
    }

    /**
     * Helper para decodificar JSON.
     */
    public static function decode_json(mixed $val): mixed
    {
        if (is_array($val) || is_object($val)) return $val;
        if (is_string($val) && $val !== '') {
            $dec = json_decode($val, true);
            return json_last_error() === JSON_ERROR_NONE ? $dec : [];
        }
        return [];
    }
}
