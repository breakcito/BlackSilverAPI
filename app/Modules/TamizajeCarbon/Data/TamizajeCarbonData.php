<?php

namespace App\Modules\TamizajeCarbon\Data;

use App\Models\StockCarbon;
use App\Models\VarianteZarandeoCarbon;
use App\Models\ZarandeoCarbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TamizajeCarbonData
{
    /**
     * Cache del chequeo de esquema para no consultarlo en cada fila.
     */
    private static ?bool $tienePorcentajeExtraccion = null;

    /**
     * La columna `porcentaje_extraccion` de variante_zarandeo_carbon se agrega
     * con SQL plano. Se detecta en runtime para no romper si el script aun no
     * se ejecuto.
     */
    private static function tienePorcentajeExtraccion(): bool
    {
        if (self::$tienePorcentajeExtraccion === null) {
            self::$tienePorcentajeExtraccion = Schema::hasColumn(
                'variante_zarandeo_carbon',
                'porcentaje_extraccion'
            );
        }

        return self::$tienePorcentajeExtraccion;
    }

    /**
     * Lista el stock de carbón por almacén, tipo de carbón y calidad.
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
                tc.para_compra as tipo_carbon_para_compra,
                s.calidad,
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

        if (!empty($opts['calidad'])) {
            $sql .= ' AND s.calidad = :calidad';
            $params['calidad'] = (string) $opts['calidad'];
        }

        $filtros = trim((string) ($opts['filtros'] ?? ''));
        if ($filtros !== '') {
            $sql .= ' AND (tc.nombre LIKE :q OR tc.codigo LIKE :q OR alm.nombre LIKE :q)';
            $params['q'] = '%' . $filtros . '%';
        }

        $sql .= ' ORDER BY tc.para_compra DESC, tc.nombre ASC, alm.nombre ASC, s.calidad ASC';

        $rows = DB::select($sql, $params);
        foreach ($rows as $row) {
            $row->cambios_log = self::decode_json($row->cambios_log ?? null);
            $row->tipo_carbon_para_compra = (bool) ($row->tipo_carbon_para_compra ?? false);
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
     * Lista los zarandeos realizados con sus variantes, cargas y gastos.
     * @param array{id_zarandeo?: int, id_almacen?: int, mes?: int, anio?: int, filtros?: string} $opts
     * @return array<object>
     */
    public static function get_tamizajes(array $opts = []): array
    {
        $sql = '
            SELECT
                tz.id AS id_zarandeo_carbon,
                tz.id_almacen,
                alm.nombre AS almacen_nombre,
                tz.id_empleado_registro,
                CONCAT(er.nombre, " ", er.apellido) AS empleado_registro,
                tz.id_empleado_supervisor,
                CONCAT(es.nombre, " ", es.apellido) AS empleado_supervisor,
                tz.id_tipo_carbon,
                tc.nombre AS tipo_carbon_nombre,
                tc.codigo AS tipo_carbon_codigo,
                tz.calidad,
                tz.cantidad_zarandeada,
                tz.cantidad_extraida,
                tz.es_rezarandeo,
                tz.fecha_hora_zarandeo,
                tz.evidencias,
                tz.created_at,
                (
                    SELECT COUNT(*)
                    FROM carga_compra_carbon c
                    WHERE c.id_zarandeo_carbon = tz.id
                ) AS cantidad_cargas,
                (
                    SELECT COALESCE(SUM(c.cantidad), 0)
                    FROM carga_compra_carbon c
                    WHERE c.id_zarandeo_carbon = tz.id
                ) AS total_cargas_cantidad
            FROM zarandeo_carbon tz
            INNER JOIN almacen alm ON alm.id = tz.id_almacen
            INNER JOIN empleado er ON er.id = tz.id_empleado_registro
            INNER JOIN tipo_carbon tc ON tc.id = tz.id_tipo_carbon
            LEFT JOIN empleado es ON es.id = tz.id_empleado_supervisor
            WHERE 1 = 1
        ';

        $params = [];

        if (!empty($opts['id_zarandeo'])) {
            $sql .= ' AND tz.id = :id_zarandeo';
            $params['id_zarandeo'] = (int) $opts['id_zarandeo'];
        }

        if (!empty($opts['id_almacen'])) {
            $sql .= ' AND tz.id_almacen = :id_almacen';
            $params['id_almacen'] = (int) $opts['id_almacen'];
        }

        $mes = isset($opts['mes']) ? (int) $opts['mes'] : 0;
        $anio = isset($opts['anio']) ? (int) $opts['anio'] : 0;
        if ($mes > 0 && $anio > 0) {
            $sql .= ' AND MONTH(tz.fecha_hora_zarandeo) = :mes AND YEAR(tz.fecha_hora_zarandeo) = :anio';
            $params['mes'] = $mes;
            $params['anio'] = $anio;
        } elseif ($anio > 0) {
            $sql .= ' AND YEAR(tz.fecha_hora_zarandeo) = :anio';
            $params['anio'] = $anio;
        }

        $filtros = trim((string) ($opts['filtros'] ?? ''));
        if ($filtros !== '') {
            $sql .= ' AND (tc.nombre LIKE :q OR alm.nombre LIKE :q OR tz.calidad LIKE :q)';
            $params['q'] = '%' . $filtros . '%';
        }

        $sql .= ' ORDER BY tz.fecha_hora_zarandeo DESC, tz.id DESC';

        $tamizajes = DB::select($sql, $params);
        foreach ($tamizajes as $tz) {
            $tz->evidencias = self::decode_json($tz->evidencias ?? null);
            $tz->es_rezarandeo = (bool) ($tz->es_rezarandeo ?? false);

            // Variantes extraídas
            $colPorcentaje = self::tienePorcentajeExtraccion()
                ? 'vt.porcentaje_extraccion'
                : '0 AS porcentaje_extraccion';

            $tz->variantes = DB::select('
                SELECT
                    vt.id AS id_variante_zarandeo_carbon,
                    vt.id_zarandeo_carbon,
                    vt.id_tipo_variante,
                    tcv.nombre AS tipo_variante_nombre,
                    tcv.codigo AS tipo_variante_codigo,
                    ' . $colPorcentaje . ',
                    vt.cantidad_extraida,
                    vt.cambios_log
                FROM variante_zarandeo_carbon vt
                INNER JOIN tipo_carbon tcv ON tcv.id = vt.id_tipo_variante
                WHERE vt.id_zarandeo_carbon = :id_tz
                ORDER BY vt.id ASC
            ', ['id_tz' => $tz->id_zarandeo_carbon]);

            foreach ($tz->variantes as $v) {
                $v->cambios_log = self::decode_json($v->cambios_log ?? null);
            }

            // Cargas que participaron en el zarandeo
            $tz->cargas = DB::select('
                SELECT
                    c.id AS id_carga_compra_carbon,
                    c.codigo_ticket_balanza,
                    c.placa,
                    c.cantidad,
                    c.porcentaje_ceniza,
                    c.calidad,
                    c.fecha_hora_ingreso,
                    cc.correlativo AS compra_correlativo
                FROM carga_compra_carbon c
                INNER JOIN compra_carbon cc ON cc.id = c.id_compra_carbon
                WHERE c.id_zarandeo_carbon = :id_tz
                ORDER BY c.id ASC
            ', ['id_tz' => $tz->id_zarandeo_carbon]);
        }

        return $tamizajes;
    }

    /**
     * Obtiene un zarandeo por su ID con sus variantes, cargas y gastos.
     */
    public static function get_tamizaje_by_id(int $id_tamizaje): ?object
    {
        $tamizajes = self::get_tamizajes(['id_zarandeo' => $id_tamizaje]);
        return $tamizajes[0] ?? null;
    }

    /**
     * Agrupa las cargas de carbón aún no zarandeadas por (almacén, tipo de carbón, calidad).
     * Cada grupo se zarea completo: el usuario elige un grupo, no cargas sueltas.
     * @param array{id_almacen?: int, id_tipo_carbon?: int} $opts
     * @return array<object>
     */
    public static function get_grupos_cargas(array $opts = []): array
    {
        $sql = '
            SELECT
                c.id_almacen_empresa_llegada AS id_almacen,
                alm.nombre AS almacen_nombre,
                c.id_tipo_carbon,
                tc.nombre AS tipo_carbon_nombre,
                tc.codigo AS tipo_carbon_codigo,
                c.calidad,
                COUNT(*) AS cantidad_cargas,
                COALESCE(SUM(c.cantidad), 0) AS cantidad_total
            FROM carga_compra_carbon c
            INNER JOIN tipo_carbon tc ON tc.id = c.id_tipo_carbon
            LEFT JOIN almacen alm ON alm.id = c.id_almacen_empresa_llegada
            WHERE c.id_zarandeo_carbon IS NULL
              AND c.estado != "Anulado"
              AND c.id_almacen_empresa_llegada IS NOT NULL
        ';

        $params = [];
        if (!empty($opts['id_almacen'])) {
            $sql .= ' AND c.id_almacen_empresa_llegada = :id_almacen';
            $params['id_almacen'] = (int) $opts['id_almacen'];
        }
        if (!empty($opts['id_tipo_carbon'])) {
            $sql .= ' AND c.id_tipo_carbon = :id_tipo_carbon';
            $params['id_tipo_carbon'] = (int) $opts['id_tipo_carbon'];
        }

        $sql .= ' GROUP BY c.id_almacen_empresa_llegada, alm.nombre, c.id_tipo_carbon, tc.nombre, tc.codigo, c.calidad';
        $sql .= ' ORDER BY tc.nombre ASC, c.calidad ASC';

        $grupos = DB::select($sql, $params);

        foreach ($grupos as $g) {
            $g->cargas = DB::select('
                SELECT
                    c.id AS id_carga_compra_carbon,
                    c.codigo_ticket_balanza,
                    c.placa,
                    c.cantidad,
                    c.porcentaje_ceniza,
                    c.calidad,
                    c.fecha_hora_ingreso,
                    cc.correlativo AS compra_correlativo
                FROM carga_compra_carbon c
                INNER JOIN compra_carbon cc ON cc.id = c.id_compra_carbon
                WHERE c.id_zarandeo_carbon IS NULL
                  AND c.estado != "Anulado"
                  AND c.id_almacen_empresa_llegada = :id_almacen
                  AND c.id_tipo_carbon = :id_tipo_carbon
                  AND ' . ($g->calidad === null ? 'c.calidad IS NULL' : 'c.calidad = :calidad') . '
                ORDER BY c.fecha_hora_ingreso ASC, c.id ASC
            ', array_filter([
                'id_almacen' => (int) $g->id_almacen,
                'id_tipo_carbon' => (int) $g->id_tipo_carbon,
                'calidad' => $g->calidad,
            ], fn ($v) => $v !== null));
        }

        return $grupos;
    }

    /**
     * Registra un zarandeo de carbón y actualiza los stocks correspondientes.
     *
     * El stock se lleva por (almacén, tipo de carbón, calidad):
     *  - se resta `cantidad_extraida` (suma de variantes) del tipo de carbón zarandeado,
     *  - se suma `cantidad_extraida` de cada variante al stock de esa variante,
     *  - todas las variantes heredan la calidad del zarandeo.
     *
     * No genera kardex: nada sale ni entra del almacén, es una transformación interna.
     *
     * @param array{
     *   id_almacen: int,
     *   id_empleado_registro: int,
     *   id_empleado_supervisor?: int|null,
     *   id_tipo_carbon: int,
     *   calidad: string,
     *   cantidad_zarandeada: float,
     *   es_rezarandeo: bool,
     *   id_cargas?: int[],
     *   fecha_hora_zarandeo: string,
     *   evidencias?: mixed,
     *   variantes: array<int, array{id_tipo_variante: int, porcentaje_extraccion: float, cantidad_extraida: float}>
     * } $d
     */
    public static function registrar_tamizaje(array $d): int
    {
        return DB::transaction(function () use ($d) {
            $idAlmacen = (int) $d['id_almacen'];
            $idTipoCarbon = (int) $d['id_tipo_carbon'];
            $calidad = (string) $d['calidad'];
            $variantes = $d['variantes'];
            $idCargas = array_values(array_unique(array_map('intval', $d['id_cargas'] ?? [])));
            $esRezarandeo = empty($idCargas);

            $cantidadExtraidaTotal = 0.0;
            foreach ($variantes as $v) {
                $cantidadExtraidaTotal += round((float) $v['cantidad_extraida'], 4);
            }

            $evidenciasJson = isset($d['evidencias']) && $d['evidencias'] !== null
                ? (is_string($d['evidencias']) ? $d['evidencias'] : json_encode($d['evidencias'], JSON_UNESCAPED_UNICODE))
                : null;

            $idTamizaje = ZarandeoCarbon::insertGetId([
                'id_almacen' => $idAlmacen,
                'id_empleado_registro' => (int) $d['id_empleado_registro'],
                'id_empleado_supervisor' => isset($d['id_empleado_supervisor']) && (int) $d['id_empleado_supervisor'] > 0
                    ? (int) $d['id_empleado_supervisor']
                    : null,
                'id_tipo_carbon' => $idTipoCarbon,
                'calidad' => $calidad,
                'cantidad_zarandeada' => (float) $d['cantidad_zarandeada'],
                'cantidad_extraida' => $cantidadExtraidaTotal,
                'es_rezarandeo' => $esRezarandeo ? 1 : 0,
                'fecha_hora_zarandeo' => (string) $d['fecha_hora_zarandeo'],
                'evidencias' => $evidenciasJson,
                'created_at' => now()->toDateTimeString(),
            ]);

            // Verificacion de stock con la fila bloqueada: evita que dos
            // zarandeos simultaneos descuarten mas de lo disponible.
            $stockDisponible = self::get_stock_disponible_bloqueado(
                idAlmacen: $idAlmacen,
                idTipoCarbon: $idTipoCarbon,
                calidad: $calidad
            );

            if ($stockDisponible + 0.0001 < (float) $d['cantidad_zarandeada']) {
                throw new \RuntimeException(
                    'No hay stock suficiente de la calidad seleccionada (disponible: '
                    . rtrim(rtrim(number_format($stockDisponible, 2), '0'), '.') . ' TN)'
                );
            }

            if ($stockDisponible + 0.0001 < $cantidadExtraidaTotal) {
                throw new \RuntimeException(
                    'El total extraído supera el stock disponible (disponible: '
                    . rtrim(rtrim(number_format($stockDisponible, 2), '0'), '.') . ' TN)'
                );
            }

            // Insertar variantes y sumar al stock de cada una
            $conPorcentaje = self::tienePorcentajeExtraccion();

            foreach ($variantes as $v) {
                $idTipoVariante = (int) $v['id_tipo_variante'];
                $cantVar = round((float) $v['cantidad_extraida'], 4);

                $filaVariante = [
                    'id_zarandeo_carbon' => $idTamizaje,
                    'id_tipo_variante' => $idTipoVariante,
                    'cantidad_extraida' => $cantVar,
                    'cambios_log' => null,
                ];

                if ($conPorcentaje) {
                    $filaVariante['porcentaje_extraccion'] = round((float) ($v['porcentaje_extraccion'] ?? 0), 4);
                }

                VarianteZarandeoCarbon::insert($filaVariante);

                // Sumar al stock de la variante en ese almacén y calidad
                self::ajustar_stock_almacen($idAlmacen, $idTipoVariante, $calidad, $cantVar);
            }

            // Restar del stock del tipo de carbón zarandeado
            self::ajustar_stock_almacen($idAlmacen, $idTipoCarbon, $calidad, -$cantidadExtraidaTotal);

            // Marcar las cargas del grupo como zarandeadas.
            // El WHERE protege contra una asignacion concurrente: si otra
            // transaccion ya las tomo, la fila no se toca y se revierte todo.
            if (!empty($idCargas)) {
                $afectadas = DB::table('carga_compra_carbon')
                    ->whereIn('id', $idCargas)
                    ->whereNull('id_zarandeo_carbon')
                    ->update(['id_zarandeo_carbon' => $idTamizaje]);

                if ($afectadas !== count($idCargas)) {
                    throw new \RuntimeException(
                        'Alguna de las cargas seleccionadas ya fue zarandeada por otra operación'
                    );
                }
            }

            return $idTamizaje;
        });
    }

    /**
     * Ajusta el stock de un tipo de carbón en un almacén y calidad (suma o resta).
     */
    public static function ajustar_stock_almacen(
        int $idAlmacen,
        int $idTipoCarbon,
        ?string $calidad,
        float $delta
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

        if ($stock === null) {
            $nuevoStock = max(0.0, round($delta, 4));
            StockCarbon::insert([
                'id_almacen' => $idAlmacen,
                'id_tipo_carbon' => $idTipoCarbon,
                'calidad' => $calidad,
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
     * Obtiene el stock disponible de un tipo de carbón en un almacén y calidad.
     */
    public static function get_stock_disponible(int $idAlmacen, int $idTipoCarbon, ?string $calidad): float
    {
        $stock = DB::table('stock_carbon')
            ->where('id_almacen', $idAlmacen)
            ->where('id_tipo_carbon', $idTipoCarbon)
            ->where(function ($q) use ($calidad) {
                $calidad === null
                    ? $q->whereNull('calidad')
                    : $q->where('calidad', $calidad);
            })
            ->first();

        return $stock !== null ? (float) $stock->stock_actual : 0.0;
    }

    /**
     * Igual que get_stock_disponible pero bloqueando la fila (uso transaccional).
     */
    private static function get_stock_disponible_bloqueado(
        int $idAlmacen,
        int $idTipoCarbon,
        ?string $calidad
    ): float {
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

        return $stock !== null ? (float) $stock->stock_actual : 0.0;
    }

    /**
     * Helper para decodificar JSON.
     */
    public static function decode_json(mixed $val): mixed
    {
        if (is_array($val) || is_object($val))
            return $val;
        if (is_string($val) && $val !== '') {
            $dec = json_decode($val, true);
            return json_last_error() === JSON_ERROR_NONE ? $dec : [];
        }
        return [];
    }
}
