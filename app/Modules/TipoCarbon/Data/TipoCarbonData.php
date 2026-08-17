<?php

namespace App\Modules\TipoCarbon\Data;

use App\Models\TipoCarbon;
use App\Models\VarianteCarbon;
use Illuminate\Support\Facades\DB;

class TipoCarbonData
{
    /**
     * Lista de tipos con conteo de variantes.
     * @return array<object>
     */
    public static function get_tipos(?int $id_tipo_carbon = null)
    {
        $sql = '
            SELECT
                t.id AS id_tipo_carbon,
                t.nombre,
                t.codigo,
                (
                    SELECT COUNT(*)
                    FROM variante_carbon vc
                    WHERE vc.id_tipo_carbon = t.id
                ) AS cantidad_variantes
            FROM tipo_carbon t
            WHERE 1 = 1
        ';

        $params = [];

        if ($id_tipo_carbon !== null) {
            $sql .= ' AND t.id = :id_tipo_carbon';
            $params['id_tipo_carbon'] = $id_tipo_carbon;
            return DB::selectOne($sql, $params);
        }

        $sql .= ' ORDER BY t.nombre ASC';
        return DB::select($sql, $params);
    }

    public static function get_tipo_by_id(int $id_tipo_carbon)
    {
        return self::get_tipos(id_tipo_carbon: $id_tipo_carbon);
    }

    public static function crear_tipo(string $nombre, ?string $codigo = null): int
    {
        return TipoCarbon::insertGetId([
            'nombre' => $nombre,
            'codigo' => $codigo,
        ]);
    }

    public static function actualizar_tipo(int $id_tipo_carbon, string $nombre, ?string $codigo = null): int
    {
        return TipoCarbon::where('id', $id_tipo_carbon)->update([
            'nombre' => $nombre,
            'codigo' => $codigo,
        ]);
    }

    /**
     * Verifica si hay referencias que impidan borrar el tipo.
     * Retorna conteo de filas donde este tipo aparece como variante de otros.
     */
    public static function contar_referencias_como_variante(int $id_tipo_carbon): int
    {
        return DB::table('variante_carbon')
            ->where('id_tipo_variante', $id_tipo_carbon)
            ->count();
    }

    /**
     * Borra el tipo y todas sus variantes propias.
     * NO debe llamarse si contar_referencias_como_variante > 0.
     */
    public static function eliminar_tipo(int $id_tipo_carbon): int
    {
        return DB::transaction(function () use ($id_tipo_carbon) {
            DB::table('variante_carbon')
                ->where('id_tipo_carbon', $id_tipo_carbon)
                ->delete();
            return TipoCarbon::where('id', $id_tipo_carbon)->delete();
        });
    }

    /**
     * Lista los nombres de las variantes de un tipo padre.
     */
    public static function get_variantes_de_tipo(int $id_tipo_carbon)
    {
        $sql = '
            SELECT
                v.id AS id_tipo_variante,
                t.nombre,
                t.codigo
            FROM variante_carbon v
            INNER JOIN tipo_carbon t ON t.id = v.id_tipo_variante
            WHERE v.id_tipo_carbon = :id_tipo_carbon
            ORDER BY t.nombre ASC
        ';
        return DB::select($sql, ['id_tipo_carbon' => $id_tipo_carbon]);
    }

    /**
     * Set masivo de variantes: borra las existentes y crea las nuevas
     * en una sola transacción. Permite que el tipo sea variante de sí mismo.
     * @param int $id_tipo_carbon
     * @param int[] $ids_variante
     */
    public static function set_variantes(int $id_tipo_carbon, array $ids_variante): void
    {
        DB::transaction(function () use ($id_tipo_carbon, $ids_variante) {
            DB::table('variante_carbon')
                ->where('id_tipo_carbon', $id_tipo_carbon)
                ->delete();

            $filas = [];
            foreach ($ids_variante as $id_variante) {
                $id_variante = (int) $id_variante;
                if ($id_variante <= 0) {
                    continue;
                }
                $filas[] = [
                    'id_tipo_carbon' => $id_tipo_carbon,
                    'id_tipo_variante' => $id_variante,
                ];
            }
            if (!empty($filas)) {
                DB::table('variante_carbon')->insert($filas);
            }
        });
    }

    /**
     * Lista todos los tipos. Usado por el selector de variantes donde
     * un tipo puede seleccionarse a si mismo como variante.
     */
    public static function get_todos_los_tipos(): array
    {
        $sql = 'SELECT id AS id_tipo_carbon, nombre, codigo FROM tipo_carbon ORDER BY nombre ASC';
        return DB::select($sql);
    }
}