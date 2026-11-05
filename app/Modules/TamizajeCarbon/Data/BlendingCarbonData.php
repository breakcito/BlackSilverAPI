<?php

namespace App\Modules\TamizajeCarbon\Data;

use App\Models\BlendingCarbon;
use App\Models\DetalleBlendingCarbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data del blending de carbon.
 *
 * Un blending toma stock de varias calidades del mismo tipo de carbon y
 * produce un lote nuevo, con su propio stock (`blending_carbon.stock_actual`).
 *
 * Reglas de stock:
 *  - El blending SOLO disminuye el stock que toma (`stock_carbon`), nunca lo
 *    aumenta: es una transformacion, no una entrada.
 *  - Si lo que se pide supera lo que hay, el stock queda en 0 (no se bloquea).
 *  - Al EDITAR un blending se aplica el DELTA de las cantidades sobre las
 *    filas de stock afectadas, de modo que la edicion es reversible.
 */
class BlendingCarbonData
{
    /**
     * Cache del chequeo de esquema: `detalle_blending_carbon.id_blending_carbon`
     * se agrega con SQL plano y se detecta en runtime.
     */
    private static ?bool $detalleTieneBlending = null;

    /**
     * ¿La tabla de detalle ya tiene la columna que la enlaza al blending?
     */
    public static function detalle_tiene_blending(): bool
    {
        if (self::$detalleTieneBlending === null) {
            self::$detalleTieneBlending = Schema::hasColumn(
                'detalle_blending_carbon',
                'id_blending_carbon'
            );
        }

        return self::$detalleTieneBlending;
    }

    /**
     * Lista los blendings con sus detalles y el resumen de sus gastos.
     *
     * @param array{id_blending?: int, id_almacen?: int, id_tipo_carbon?: int, mes?: int, anio?: int, filtros?: string} $opts
     * @return array<object>
     */
    public static function get_blendings(array $opts = []): array
    {
        // El contador de detalle depende de una columna que se agrega con SQL
        // plano: si todavia no existe, el listado sigue funcionando.
        $contadorDetalles = self::detalle_tiene_blending()
            ? '(
                    SELECT COUNT(*)
                    FROM detalle_blending_carbon d
                    WHERE d.id_blending_carbon = b.id
                ) AS cantidad_detalles'
            : '0 AS cantidad_detalles';

        $sql = '
            SELECT
                b.id AS id_blending_carbon,
                b.correlativo,
                b.numero_correlativo,
                b.id_almacen,
                alm.nombre AS almacen_nombre,
                b.id_tipo_carbon,
                tc.nombre AS tipo_carbon_nombre,
                tc.codigo AS tipo_carbon_codigo,
                b.id_empleado_registro,
                CONCAT(er.nombre, " ", er.apellido) AS empleado_registro,
                b.id_empleado_supervisor,
                CONCAT(es.nombre, " ", es.apellido) AS empleado_supervisor,
                b.porcentaje_ceniza,
                b.stock_actual,
                b.fecha_hora_blending,
                b.evidencias,
                b.estado,
                b.created_at,
                ' . $contadorDetalles . ',
                (
                    SELECT COUNT(*)
                    FROM gasto_produccion_carbon g
                    WHERE g.id_blending_carbon = b.id
                ) AS gastos_cantidad,
                (
                    SELECT COALESCE(SUM(g.total), 0)
                    FROM gasto_produccion_carbon g
                    WHERE g.id_blending_carbon = b.id
                ) AS gastos_total
            FROM blending_carbon b
            LEFT JOIN almacen alm ON alm.id = b.id_almacen
            LEFT JOIN tipo_carbon tc ON tc.id = b.id_tipo_carbon
            LEFT JOIN empleado er ON er.id = b.id_empleado_registro
            LEFT JOIN empleado es ON es.id = b.id_empleado_supervisor
            WHERE 1 = 1
        ';

        $params = [];

        if (!empty($opts['id_blending'])) {
            $sql .= ' AND b.id = :id_blending';
            $params['id_blending'] = (int) $opts['id_blending'];
        }

        if (!empty($opts['id_almacen'])) {
            $sql .= ' AND b.id_almacen = :id_almacen';
            $params['id_almacen'] = (int) $opts['id_almacen'];
        }

        if (!empty($opts['id_tipo_carbon'])) {
            $sql .= ' AND b.id_tipo_carbon = :id_tipo_carbon';
            $params['id_tipo_carbon'] = (int) $opts['id_tipo_carbon'];
        }

        $mes = isset($opts['mes']) ? (int) $opts['mes'] : 0;
        $anio = isset($opts['anio']) ? (int) $opts['anio'] : 0;
        if ($mes > 0 && $anio > 0) {
            $sql .= ' AND MONTH(b.fecha_hora_blending) = :mes AND YEAR(b.fecha_hora_blending) = :anio';
            $params['mes'] = $mes;
            $params['anio'] = $anio;
        } elseif ($anio > 0) {
            $sql .= ' AND YEAR(b.fecha_hora_blending) = :anio';
            $params['anio'] = $anio;
        }

        $filtros = trim((string) ($opts['filtros'] ?? ''));
        if ($filtros !== '') {
            $sql .= ' AND (b.correlativo LIKE :q1 OR tc.nombre LIKE :q2 OR alm.nombre LIKE :q3)';
            $like = '%' . $filtros . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        $sql .= ' ORDER BY b.fecha_hora_blending DESC, b.id DESC';

        $rows = DB::select($sql, $params);

        // Los detalles se piden en UNA sola consulta para no caer en N+1.
        $detallesPorBlending = self::get_detalles_por_blending(
            array_map(fn ($r) => (int) $r->id_blending_carbon, $rows)
        );

        foreach ($rows as $row) {
            $row->evidencias = self::decode_json($row->evidencias ?? null);
            $row->detalles = $detallesPorBlending[(int) $row->id_blending_carbon] ?? [];
        }

        return $rows;
    }

    /**
     * Obtiene un blending por su id con sus detalles.
     */
    public static function get_blending_by_id(int $id_blending): ?object
    {
        $blendings = self::get_blendings(['id_blending' => $id_blending]);
        return $blendings[0] ?? null;
    }

    /**
     * Detalles (stock tomado) de un blending.
     *
     * @return array<object>
     */
    public static function get_detalles(int $id_blending): array
    {
        $porBlending = self::get_detalles_por_blending([$id_blending]);

        return $porBlending[$id_blending] ?? [];
    }

    /**
     * Detalles de varios blendings en una sola consulta (evita el N+1 del
     * listado).
     *
     * @param array<int, int> $idsBlending
     * @return array<int, array<object>> id de blending => detalles
     */
    public static function get_detalles_por_blending(array $idsBlending): array
    {
        if (!self::detalle_tiene_blending()) {
            return [];
        }

        $ids = array_values(array_unique(array_map('intval', $idsBlending)));
        $ids = array_values(array_filter($ids, fn ($id) => $id > 0));

        if (empty($ids)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $key = 'id_blending_' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        $rows = DB::select('
            SELECT
                d.id AS id_detalle_blending_carbon,
                d.id_blending_carbon,
                d.id_stock_carbon,
                s.calidad,
                s.stock_actual,
                s.id_tipo_carbon,
                tc.nombre AS tipo_carbon_nombre,
                tc.codigo AS tipo_carbon_codigo,
                d.cantidad_tomada
            FROM detalle_blending_carbon d
            LEFT JOIN stock_carbon s ON s.id = d.id_stock_carbon
            LEFT JOIN tipo_carbon tc ON tc.id = s.id_tipo_carbon
            WHERE d.id_blending_carbon IN (' . implode(',', $placeholders) . ')
            ORDER BY d.id ASC
        ', $params);

        $resultado = [];
        foreach ($rows as $row) {
            $resultado[(int) $row->id_blending_carbon][] = $row;
        }

        return $resultado;
    }

    /**
     * Registra un blending con sus detalles y descuenta el stock tomado.
     *
     * @param array{
     *   id_almacen: int,
     *   id_empleado_registro: int,
     *   id_empleado_supervisor?: int|null,
     *   id_tipo_carbon: int,
     *   porcentaje_ceniza: float,
     *   fecha_hora_blending?: string,
     *   evidencias?: mixed,
     *   detalles: array<int, array{id_stock_carbon: int, cantidad_tomada: float}>
     * } $d
     */
    public static function insert_blending(array $d): int
    {
        return DB::transaction(function () use ($d) {
            $correlativo = self::generar_correlativo();

            $cantidadTotal = 0.0;
            foreach ($d['detalles'] as $detalle) {
                $cantidadTotal += round((float) $detalle['cantidad_tomada'], 4);
            }

            $evidenciasJson = isset($d['evidencias']) && $d['evidencias'] !== null
                ? (is_string($d['evidencias']) ? $d['evidencias'] : json_encode($d['evidencias'], JSON_UNESCAPED_UNICODE))
                : null;

            $idBlending = BlendingCarbon::insertGetId([
                'id_almacen' => (int) $d['id_almacen'],
                'id_empleado_registro' => (int) $d['id_empleado_registro'],
                'id_empleado_supervisor' => isset($d['id_empleado_supervisor']) && (int) $d['id_empleado_supervisor'] > 0
                    ? (int) $d['id_empleado_supervisor']
                    : null,
                'id_tipo_carbon' => (int) $d['id_tipo_carbon'],
                'correlativo' => $correlativo['correlativo'],
                'numero_correlativo' => $correlativo['numero_correlativo'],
                'porcentaje_ceniza' => round((float) $d['porcentaje_ceniza'], 4),
                'stock_actual' => $cantidadTotal,
                'fecha_hora_blending' => (string) ($d['fecha_hora_blending'] ?? now()->toDateTimeString()),
                'evidencias' => $evidenciasJson,
                'created_at' => now()->toDateTimeString(),
                'estado' => 'Activo',
            ]);

            foreach ($d['detalles'] as $detalle) {
                $cantidad = round((float) $detalle['cantidad_tomada'], 4);
                if ($cantidad <= 0) {
                    continue;
                }

                DetalleBlendingCarbon::insert([
                    'id_blending_carbon' => $idBlending,
                    'id_stock_carbon' => (int) $detalle['id_stock_carbon'],
                    'cantidad_tomada' => $cantidad,
                ]);

                self::ajustar_stock_carbon((int) $detalle['id_stock_carbon'], -$cantidad);
            }

            return $idBlending;
        });
    }

    /**
     * Actualiza la cabecera y el stock tomado de un blending existente.
     *
     * El stock se recalcula aplicando el DELTA: lo que se deja de tomar se
     * devuelve al stock y lo que se toma de mas se descuenta. Asi la edicion
     * es reversible y no deja stock "colgado".
     *
     * @param array{
     *   id_blending_carbon: int,
     *   id_empleado_supervisor?: int|null,
     *   id_tipo_carbon: int,
     *   porcentaje_ceniza: float,
     *   fecha_hora_blending?: string,
     *   evidencias?: mixed,
     *   detalles: array<int, array{id_stock_carbon: int, cantidad_tomada: float}>
     * } $d
     */
    public static function update_blending(array $d): void
    {
        DB::transaction(function () use ($d) {
            $idBlending = (int) $d['id_blending_carbon'];

            $blending = DB::table('blending_carbon')
                ->where('id', $idBlending)
                ->lockForUpdate()
                ->first();

            if ($blending === null) {
                throw new \RuntimeException('El blending indicado no existe');
            }

            $anteriores = self::get_detalles($idBlending);

            // Se acumulan los deltas por fila de stock: una misma fila puede
            // aparecer en el detalle viejo y en el nuevo.
            $deltaPorStock = [];

            foreach ($anteriores as $anterior) {
                $idStock = (int) $anterior->id_stock_carbon;
                $deltaPorStock[$idStock] = ($deltaPorStock[$idStock] ?? 0)
                    + round((float) $anterior->cantidad_tomada, 4);
            }

            foreach ($d['detalles'] as $detalle) {
                $idStock = (int) $detalle['id_stock_carbon'];
                $deltaPorStock[$idStock] = ($deltaPorStock[$idStock] ?? 0)
                    - round((float) $detalle['cantidad_tomada'], 4);
            }

            foreach ($deltaPorStock as $idStock => $delta) {
                if (abs($delta) < 0.0001) {
                    continue;
                }
                self::ajustar_stock_carbon((int) $idStock, $delta);
            }

            // El detalle se rehace por completo: es la version vigente de lo
            // que el blending tomo del stock.
            DB::table('detalle_blending_carbon')
                ->where('id_blending_carbon', $idBlending)
                ->delete();

            $cantidadTotal = 0.0;
            foreach ($d['detalles'] as $detalle) {
                $cantidad = round((float) $detalle['cantidad_tomada'], 4);
                if ($cantidad <= 0) {
                    continue;
                }

                DetalleBlendingCarbon::insert([
                    'id_blending_carbon' => $idBlending,
                    'id_stock_carbon' => (int) $detalle['id_stock_carbon'],
                    'cantidad_tomada' => $cantidad,
                ]);

                $cantidadTotal += $cantidad;
            }

            $actualizaciones = [
                'id_tipo_carbon' => (int) $d['id_tipo_carbon'],
                'porcentaje_ceniza' => round((float) $d['porcentaje_ceniza'], 4),
                'stock_actual' => $cantidadTotal,
                'fecha_hora_blending' => (string) ($d['fecha_hora_blending'] ?? now()->toDateTimeString()),
            ];

            if (isset($d['id_empleado_supervisor'])) {
                $actualizaciones['id_empleado_supervisor'] = (int) $d['id_empleado_supervisor'] > 0
                    ? (int) $d['id_empleado_supervisor']
                    : null;
            }

            if (array_key_exists('evidencias', $d)) {
                $actualizaciones['evidencias'] = self::encode_json($d['evidencias']);
            }

            DB::table('blending_carbon')
                ->where('id', $idBlending)
                ->update($actualizaciones);
        });
    }

    /**
     * Agrega mas stock tomado a un blending ya registrado.
     *
     * Si la fila de stock ya existe en el detalle, se acumula la cantidad;
     * si no, se agrega un detalle nuevo. El stock se descuenta en el mismo
     * movimiento.
     *
     * @param array<int, array{id_stock_carbon: int, cantidad_tomada: float}> $detalles
     */
    public static function agregar_detalles(int $idBlending, array $detalles): void
    {
        DB::transaction(function () use ($idBlending, $detalles) {
            $blending = DB::table('blending_carbon')
                ->where('id', $idBlending)
                ->lockForUpdate()
                ->first();

            if ($blending === null) {
                throw new \RuntimeException('El blending indicado no existe');
            }

            $existentes = self::get_detalles($idBlending);
            $porStock = [];
            foreach ($existentes as $existente) {
                $porStock[(int) $existente->id_stock_carbon] = $existente;
            }

            $suma = 0.0;

            foreach ($detalles as $detalle) {
                $cantidad = round((float) $detalle['cantidad_tomada'], 4);
                if ($cantidad <= 0) {
                    continue;
                }

                $idStock = (int) $detalle['id_stock_carbon'];

                if (isset($porStock[$idStock])) {
                    $nuevaCantidad = round((float) $porStock[$idStock]->cantidad_tomada + $cantidad, 4);

                    DB::table('detalle_blending_carbon')
                        ->where('id', $porStock[$idStock]->id_detalle_blending_carbon)
                        ->update(['cantidad_tomada' => $nuevaCantidad]);
                } else {
                    DetalleBlendingCarbon::insert([
                        'id_blending_carbon' => $idBlending,
                        'id_stock_carbon' => $idStock,
                        'cantidad_tomada' => $cantidad,
                    ]);
                }

                self::ajustar_stock_carbon($idStock, -$cantidad);

                $suma += $cantidad;
            }

            DB::table('blending_carbon')
                ->where('id', $idBlending)
                ->update([
                    'stock_actual' => round((float) $blending->stock_actual + $suma, 4),
                ]);
        });
    }

    /**
     * Ajusta el stock de una fila de `stock_carbon` (suma o resta).
     *
     * El piso es cero: si se pide mas de lo que hay, queda en 0.
     */
    public static function ajustar_stock_carbon(int $id_stock_carbon, float $delta): void
    {
        $stock = DB::table('stock_carbon')
            ->where('id', $id_stock_carbon)
            ->lockForUpdate()
            ->first();

        if ($stock === null) {
            throw new \RuntimeException('La fila de stock indicada no existe');
        }

        $nuevoStock = max(0.0, round(((float) $stock->stock_actual) + $delta, 4));

        DB::table('stock_carbon')
            ->where('id', $id_stock_carbon)
            ->update(['stock_actual' => $nuevoStock]);
    }

    /**
     * Stock fisico de una fila de `stock_carbon`.
     */
    public static function stock_de_fila(int $id_stock_carbon): float
    {
        $valor = DB::table('stock_carbon')
            ->where('id', $id_stock_carbon)
            ->value('stock_actual');

        return $valor !== null ? (float) $valor : 0.0;
    }

    /**
     * Genera el siguiente correlativo de blending (LT-1, LT-2, ...).
     *
     * No se resetea por anio y no lleva ceros a la izquierda.
     *
     * @return array{correlativo: string, numero_correlativo: int}
     */
    public static function generar_correlativo(): array
    {
        $max = DB::table('blending_carbon')
            ->lockForUpdate()
            ->max('numero_correlativo');

        $siguiente = ((int) $max) + 1;

        return [
            'correlativo' => 'LT-' . $siguiente,
            'numero_correlativo' => $siguiente,
        ];
    }

    /**
     * Verifica que el blending exista.
     */
    public static function existe_blending(int $id_blending): bool
    {
        return DB::table('blending_carbon')->where('id', $id_blending)->exists();
    }

    /**
     * Verifica que el empleado exista.
     */
    public static function existe_empleado(int $id_empleado): bool
    {
        return DB::table('empleado')->where('id', $id_empleado)->exists();
    }

    /**
     * Verifica que el tipo de carbon exista.
     */
    public static function existe_tipo_carbon(int $id_tipo_carbon): bool
    {
        return DB::table('tipo_carbon')->where('id', $id_tipo_carbon)->exists();
    }

    /**
     * Fila de `stock_carbon` por su id.
     */
    public static function get_stock_carbon(int $id_stock_carbon): ?object
    {
        return DB::table('stock_carbon')->where('id', $id_stock_carbon)->first();
    }

    /**
     * Almacen de carbon por defecto: el primero habilitado para carbon.
     */
    public static function almacen_carbon_por_defecto(): int
    {
        $almacen = DB::table('almacen')
            ->where('para_carbon', 1)
            ->where(function ($q) {
                $q->whereNull('estado')->orWhere('estado', 'Activo');
            })
            ->orderBy('id', 'asc')
            ->first();

        return $almacen !== null ? (int) $almacen->id : 0;
    }

    /**
     * Blendings despachables de un almacen, con su stock ya descontando lo
     * que salio y sigue en camino.
     *
     * Lo consume el modulo de venta de carbon: una carga de venta aponta a
     * un blending, no a un par (tipo, calidad).
     *
     * @return array<object>
     */
    public static function get_blendings_despachables(int $id_almacen): array
    {
        $rows = DB::select('
            SELECT
                b.id AS id_blending_carbon,
                b.correlativo,
                b.id_almacen,
                b.id_tipo_carbon,
                tc.nombre AS tipo_carbon_nombre,
                tc.codigo AS tipo_carbon_codigo,
                b.porcentaje_ceniza,
                b.stock_actual,
                b.fecha_hora_blending,
                (
                    SELECT COALESCE(SUM(cv.cantidad_toneladas), 0)
                    FROM carga_venta_carbon cv
                    INNER JOIN venta_carbon v ON v.id = cv.id_venta_carbon
                    WHERE cv.id_blending_carbon = b.id
                      AND cv.estado = :estado_en_camino
                      AND v.id_almacen_empresa_salida = :id_almacen_comprometido
                ) AS comprometido
            FROM blending_carbon b
            LEFT JOIN tipo_carbon tc ON tc.id = b.id_tipo_carbon
            WHERE b.id_almacen = :id_almacen
              AND (b.estado IS NULL OR b.estado = "Activo")
              AND b.stock_actual > 0
            ORDER BY b.id DESC
        ', [
            'id_almacen' => $id_almacen,
            // El mismo valor con otro nombre: los prepares nativos de PDO no
            // admiten reutilizar un placeholder con nombre.
            'id_almacen_comprometido' => $id_almacen,
            'estado_en_camino' => \App\Shared\Enums\VentaCarbon\EstadoCargaVentaCarbon::EnCamino->value,
        ]);

        $despachables = [];

        foreach ($rows as $row) {
            $row->disponible = max(
                0.0,
                round((float) $row->stock_actual - (float) ($row->comprometido ?? 0), 4)
            );

            // Solo se devuelven los blendings que AUN tienen stock: si lo
            // que ya salio y sigue en camino se lo comio, no hay nada mas
            // que despachar de ese lote.
            if ($row->disponible > 0) {
                $despachables[] = $row;
            }
        }

        return $despachables;
    }

    /**
     * Datos de un blending por su id (para el modulo de venta de carbon).
     */
    public static function get_blending(int $id_blending): ?object
    {
        return DB::table('blending_carbon')->where('id', $id_blending)->first();
    }

    /**
     * Actualiza las evidencias (JSON) de un blending.
     */
    public static function actualizar_evidencias(int $id_blending_carbon, array $evidencias): void
    {
        DB::table('blending_carbon')
            ->where('id', $id_blending_carbon)
            ->update(['evidencias' => self::encode_json($evidencias)]);
    }

    /**
     * Serializa a JSON las evidencias.
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
     * Helper para decodificar JSON.
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
}
