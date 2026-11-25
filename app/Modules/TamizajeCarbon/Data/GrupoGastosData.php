<?php

namespace App\Modules\TamizajeCarbon\Data;

use App\Models\GrupoGastos;
use App\Shared\Enums\Carbon\TipoOperacionGrupoGastos;
use Illuminate\Support\Facades\DB;

/**
 * SQL de los grupos de gastos de produccion y de sus miembros.
 *
 * Un grupo reune varios zarandeos (o varios blendings) de una misma
 * operacion. Los gastos se cuelgan del grupo, no de cada operacion: asi el
 * usuario registra los gastos de la operacion completa una sola vez.
 *
 * El grupo no tiene fecha propia: la operacion agrupada la define el conjunto
 * de sus miembros (la fecha del primero y la lista de operaciones).
 */
class GrupoGastosData
{
    /**
     * Subconsulta con el agregado de los miembros de cada grupo:
     * `fecha_inicio` es la fecha de la primera operacion del grupo.
     */
    private static function sql_miembros(): string
    {
        return '
            SELECT
                id_grupo_gastos,
                MIN(fecha) AS fecha_inicio
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
        ';
    }

    /**
     * Grupos de gastos de un tipo de operacion con sus totales.
     *
     * @param string $tipo TipoOperacionGrupoGastos: 'zarandeo' | 'blending'
     * @return array<object>
     */
    public static function get_grupos(string $tipo): array
    {
        $sql = '
            SELECT
                gg.id AS id_grupo_gastos,
                gg.tipo,
                gm.fecha_inicio,
                (
                    SELECT COUNT(*)
                    FROM gasto_produccion_carbon g
                    WHERE g.id_grupo_gastos = gg.id
                ) AS gastos_cantidad,
                (
                    SELECT COALESCE(SUM(g.total), 0)
                    FROM gasto_produccion_carbon g
                    WHERE g.id_grupo_gastos = gg.id
                ) AS gastos_total,
                (
                    SELECT COALESCE(SUM(g.total), 0)
                    FROM gasto_produccion_carbon g
                    WHERE g.id_grupo_gastos = gg.id
                      AND g.esta_pagado = 0
                ) AS gastos_pendientes
            FROM grupo_gastos gg
            LEFT JOIN (' . self::sql_miembros() . ') gm ON gm.id_grupo_gastos = gg.id
            WHERE gg.tipo = :tipo
            ORDER BY gg.created_at DESC, gg.id DESC
        ';

        return DB::select($sql, ['tipo' => $tipo]);
    }

    /**
     * Obtiene un grupo por su id (solo el tipo: define a que operacion
     * pertenece y, por tanto, contra que tipo de gastos se registra).
     */
    public static function get_grupo(int $id_grupo_gastos): ?object
    {
        $fila = DB::selectOne('
            SELECT
                gg.id AS id_grupo_gastos,
                gg.tipo
            FROM grupo_gastos gg
            WHERE gg.id = :id
            LIMIT 1
        ', ['id' => $id_grupo_gastos]);

        return $fila ?? null;
    }

    /**
     * Crea un grupo y le asigna sus miembros en una sola transaccion.
     *
     * @param array<int, int> $ids_miembros
     */
    public static function crear_grupo(
        string $tipo,
        array $ids_miembros,
        int $id_empleado
    ): int {
        return DB::transaction(function () use ($tipo, $ids_miembros, $id_empleado) {
            $idGrupo = GrupoGastos::insertGetId([
                'tipo' => $tipo,
                'id_empleado_registro' => $id_empleado,
                'created_at' => now()->toDateTimeString(),
            ]);

            self::asignar_miembros($idGrupo, $tipo, $ids_miembros);

            return $idGrupo;
        });
    }

    /**
     * Asigna (o reasigna) los miembros de un grupo.
     *
     * @param array<int, int> $ids_miembros
     */
    public static function asignar_miembros(int $id_grupo_gastos, string $tipo, array $ids_miembros): void
    {
        if (empty($ids_miembros)) {
            return;
        }

        $tabla = $tipo === TipoOperacionGrupoGastos::Blending->value
            ? 'blending_carbon'
            : 'zarandeo_carbon';

        DB::table($tabla)
            ->whereIn('id', $ids_miembros)
            ->update(['id_grupo_gastos' => $id_grupo_gastos]);
    }

    /**
     * Verifica que un grupo exista y sea del tipo indicado.
     */
    public static function existe_grupo(int $id_grupo_gastos, string $tipo): bool
    {
        return DB::table('grupo_gastos')
            ->where('id', $id_grupo_gastos)
            ->where('tipo', $tipo)
            ->exists();
    }

    /**
     * Id del grupo al que pertenece una operacion, o null si no tiene.
     */
    public static function get_grupo_de_miembro(int $id_miembro, string $tipo): ?int
    {
        $tabla = $tipo === TipoOperacionGrupoGastos::Blending->value
            ? 'blending_carbon'
            : 'zarandeo_carbon';

        $fila = DB::table($tabla)
            ->where('id', $id_miembro)
            ->select('id_grupo_gastos')
            ->first();

        if ($fila === null || $fila->id_grupo_gastos === null) {
            return null;
        }

        return (int) $fila->id_grupo_gastos;
    }
}
