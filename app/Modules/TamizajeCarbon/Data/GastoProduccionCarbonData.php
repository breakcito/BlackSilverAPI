<?php

namespace App\Modules\TamizajeCarbon\Data;

use App\Models\GastoProduccionCarbon;
use App\Shared\Enums\Carbon\TipoOperacionGrupoGastos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SQL de los gastos de produccion de carbon.
 *
 * Un gasto cuelga de un GRUPO de gastos (que reune varios zarandeos o varios
 * blendings de una misma operacion), no de una operacion puntual.
 */
class GastoProduccionCarbonData
{
    /**
     * Expresiones SQL de las columnas de `gasto_produccion_carbon` que pueden
     * no existir todavia en el esquema.
     *
     * Mismo criterio que el resto del modulo: si la columna no esta, se
     * devuelve un literal neutro en vez de romper la consulta.
     *
     * @return array<string, string>
     */
    private static function columnas_gasto(): array
    {
        static $resueltas = null;

        if ($resueltas !== null) {
            return $resueltas;
        }

        $existentes = array_flip(Schema::getColumnListing('gasto_produccion_carbon'));

        $resueltas = [];
        foreach ([
            'esta_pagado' => '0 AS esta_pagado',
            'fecha_pago' => 'NULL AS fecha_pago',
            'medio_pago' => 'NULL AS medio_pago',
        ] as $columna => $alternativa) {
            $resueltas[$columna] = isset($existentes[$columna])
                ? 'g.' . $columna
                : $alternativa;
        }

        return $resueltas;
    }

    /**
     * Lista los gastos de produccion de un grupo.
     *
     * @param ?int $id_gasto Si se pasa, devuelve solo ese gasto (con la misma
     *   forma que el listado) en lugar de la lista completa.
     * @return array<object>|object|null
     */
    public static function get_gastos(?int $id_grupo_gastos = null, ?int $id_gasto = null)
    {
        $cols = self::columnas_gasto();

        $sql = '
            SELECT
                g.id AS id_gasto_produccion_carbon,
                g.id_grupo_gastos,
                g.id_empleado_registro,
                CONCAT(e.nombre, " ", e.apellido) AS empleado_registro,
                g.id_proveedor,
                p.razon_social AS proveedor_razon_social,
                g.gasto,
                g.otro_gasto,
                g.descripcion,
                g.fecha,
                g.numero_reporte,
                g.hora_inicio,
                g.hora_fin,
                g.cantidad,
                g.precio_unitario,
                g.total,
                ' . $cols['esta_pagado'] . ',
                ' . $cols['fecha_pago'] . ',
                ' . $cols['medio_pago'] . ',
                g.created_at
            FROM gasto_produccion_carbon g
            INNER JOIN empleado e ON e.id = g.id_empleado_registro
            LEFT JOIN proveedor p ON p.id = g.id_proveedor
            WHERE 1 = 1
        ';

        $params = [];

        if ($id_grupo_gastos !== null) {
            $sql .= ' AND g.id_grupo_gastos = :id_grupo_gastos';
            $params['id_grupo_gastos'] = $id_grupo_gastos;
        }

        if ($id_gasto !== null) {
            $sql .= ' AND g.id = :id_gasto';
            $params['id_gasto'] = $id_gasto;

            return DB::selectOne($sql, $params);
        }

        $sql .= ' ORDER BY g.fecha ASC, g.id ASC';

        return DB::select($sql, $params);
    }

    /**
     * Obtiene un gasto de produccion por su id, con la misma forma que el
     * listado (para que el front lo inserte en su lista sin volver a listar).
     */
    public static function get_gasto_by_id(int $id_gasto)
    {
        return self::get_gastos(id_gasto: $id_gasto);
    }

    /**
     * Inserta un gasto de produccion en un grupo.
     *
     * @param array<string, mixed> $d
     */
    public static function insert_gasto(array $d): int
    {
        return GastoProduccionCarbon::insertGetId([
            'id_empleado_registro' => (int) $d['id_empleado_registro'],
            'id_grupo_gastos' => (int) $d['id_grupo_gastos'],
            'id_proveedor' => isset($d['id_proveedor']) && (int) $d['id_proveedor'] > 0
                ? (int) $d['id_proveedor']
                : null,
            'gasto' => (string) $d['gasto'],
            'otro_gasto' => isset($d['otro_gasto']) && $d['otro_gasto'] !== ''
                ? (string) $d['otro_gasto']
                : null,
            'descripcion' => isset($d['descripcion']) && $d['descripcion'] !== ''
                ? (string) $d['descripcion']
                : null,
            'fecha' => (string) $d['fecha'],
            'numero_reporte' => isset($d['numero_reporte']) && $d['numero_reporte'] !== ''
                ? (string) $d['numero_reporte']
                : null,
            'hora_inicio' => isset($d['hora_inicio']) && $d['hora_inicio'] !== null && $d['hora_inicio'] !== ''
                ? (string) $d['hora_inicio']
                : null,
            'hora_fin' => isset($d['hora_fin']) && $d['hora_fin'] !== null && $d['hora_fin'] !== ''
                ? (string) $d['hora_fin']
                : null,
            'cantidad' => (float) ($d['cantidad'] ?? 0),
            'precio_unitario' => (float) ($d['precio_unitario'] ?? 0),
            'total' => (float) ($d['total'] ?? 0),
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Gastos de produccion listos para exportar.
     *
     * Ademas de los datos del gasto, trae lo que el reporte necesita y que
     * vive en otras tablas:
     *  - la FECHA INICIO de la operacion agrupada (la del primer miembro),
     *  - el MES de esa fecha,
     *  - el REGISTRO al que pertenece (los zarandeos o blendings del grupo),
     *  - el RUC y la razon social del PROVEEDOR.
     *
     * @param ?string $tipo "blending" o "zarandeo". Null = de los dos.
     * @return array<object>
     */
    public static function get_gastos_exportar(
        ?int $id_grupo_gastos = null,
        ?string $fecha_inicio = null,
        ?string $fecha_fin = null,
        ?string $tipo = null,
        ?int $mes = null,
        ?int $anio = null
    ) {
        $cols = self::columnas_gasto();

        $sql = '
            SELECT
                g.id AS id_gasto_produccion_carbon,
                g.id_grupo_gastos,
                gg.tipo AS origen,
                COALESCE(gm.registros, CONCAT("Grupo #", gg.id)) AS registro,
                gm.fecha_inicio,
                g.gasto,
                g.otro_gasto,
                g.descripcion,
                g.numero_reporte,
                g.fecha,
                p.ruc AS proveedor_ruc,
                p.dni AS proveedor_dni,
                p.razon_social AS proveedor_razon_social,
                g.cantidad,
                g.precio_unitario,
                g.total,
                ' . $cols['esta_pagado'] . ',
                ' . $cols['fecha_pago'] . ',
                ' . $cols['medio_pago'] . '
            FROM gasto_produccion_carbon g
            INNER JOIN grupo_gastos gg ON gg.id = g.id_grupo_gastos
            LEFT JOIN proveedor p ON p.id = g.id_proveedor
            LEFT JOIN (
                SELECT
                    id_grupo_gastos,
                    MIN(fecha) AS fecha_inicio,
                    GROUP_CONCAT(etiqueta ORDER BY etiqueta SEPARATOR ", ") AS registros
                FROM (
                    SELECT
                        id_grupo_gastos,
                        fecha_hora_zarandeo AS fecha,
                        CONCAT("Zarandeo #", id) AS etiqueta
                    FROM zarandeo_carbon
                    WHERE id_grupo_gastos IS NOT NULL
                    UNION ALL
                    SELECT
                        id_grupo_gastos,
                        fecha_hora_blending AS fecha,
                        COALESCE(correlativo, CONCAT("Blending #", id)) AS etiqueta
                    FROM blending_carbon
                    WHERE id_grupo_gastos IS NOT NULL
                ) u
                GROUP BY id_grupo_gastos
            ) gm ON gm.id_grupo_gastos = gg.id
            WHERE 1 = 1
        ';

        $params = [];

        if ($id_grupo_gastos !== null) {
            $sql .= ' AND g.id_grupo_gastos = :id_grupo_gastos';
            $params['id_grupo_gastos'] = $id_grupo_gastos;
        }

        // El export general de cada vista (zarandeo / blending) trae SOLO los
        // gastos de ese tipo.
        if ($tipo === TipoOperacionGrupoGastos::Blending->value) {
            $sql .= ' AND gg.tipo = :tipo';
            $params['tipo'] = $tipo;
        } elseif ($tipo === TipoOperacionGrupoGastos::Zarandeo->value) {
            $sql .= ' AND gg.tipo = :tipo';
            $params['tipo'] = $tipo;
        }

        // El rango se aplica sobre la fecha inicio de la operacion agrupada.
        if ($fecha_inicio !== null) {
            $sql .= ' AND DATE(gm.fecha_inicio) >= :fecha_inicio';
            $params['fecha_inicio'] = $fecha_inicio;
        }

        if ($fecha_fin !== null) {
            $sql .= ' AND DATE(gm.fecha_inicio) <= :fecha_fin';
            $params['fecha_fin'] = $fecha_fin;
        }

        // Filtro por mes y ano de la operacion agrupada.
        if ($mes !== null && $mes > 0 && $anio !== null && $anio > 0) {
            $sql .= ' AND MONTH(gm.fecha_inicio) = :mes AND YEAR(gm.fecha_inicio) = :anio';
            $params['mes'] = $mes;
            $params['anio'] = $anio;
        } elseif ($anio !== null && $anio > 0) {
            $sql .= ' AND YEAR(gm.fecha_inicio) = :anio';
            $params['anio'] = $anio;
        }

        $sql .= ' ORDER BY gm.fecha_inicio ASC, g.id ASC';

        return DB::select($sql, $params);
    }

    /**
     * Verifica que el proveedor exista.
     */
    public static function existe_proveedor(int $id_proveedor): bool
    {
        return DB::table('proveedor')
            ->where('id', $id_proveedor)
            ->exists();
    }

    /**
     * Verifica que el gasto exista.
     */
    public static function existe_gasto(int $id_gasto): bool
    {
        return DB::table('gasto_produccion_carbon')
            ->where('id', $id_gasto)
            ->exists();
    }

    /**
     * Marca un gasto como pagado.
     *
     * La fecha de pago puede ser retroactiva: se guarda tal como lo digita
     * el usuario. El medio de pago es obligatorio.
     */
    public static function actualizar_pago_gasto(int $id_gasto, string $fecha_pago, string $medio_pago): bool
    {
        $columnas = self::columnas_gasto();

        // Si el esquema todavia no tiene las columnas, no se puede marcar el
        // pago: se avisa en vez de fallar en silencio.
        if ($columnas['esta_pagado'] !== 'g.esta_pagado' || $columnas['fecha_pago'] !== 'g.fecha_pago') {
            return false;
        }

        $datos = [
            'esta_pagado' => 1,
            'fecha_pago' => $fecha_pago,
        ];

        if ($columnas['medio_pago'] === 'g.medio_pago') {
            $datos['medio_pago'] = $medio_pago;
        }

        $filas = DB::table('gasto_produccion_carbon')
            ->where('id', $id_gasto)
            ->update($datos);

        return $filas > 0;
    }
}
