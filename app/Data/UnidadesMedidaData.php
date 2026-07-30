<?php

namespace App\Data;

use App\Models\UnidadMedida;
use Illuminate\Support\Facades\DB;

class UnidadesMedidaData
{
    /**
     * Metodo generico para obtener unidades de medida
     */
    public static function get_unidades(
        ?int $id_unidad_medida = null,
    ) {
        $sql = '
        SELECT
            id AS id_unidad_medida,
            nombre,
            abreviatura
        FROM unidad_medida
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_unidad_medida) {
            $sql .= " AND id = :id_unidad_medida";
            $params['id_unidad_medida'] = $id_unidad_medida;
            return DB::selectOne($sql, $params);
        }

        $sql .= " ORDER BY nombre ASC";
        return DB::select($sql, $params);
    }

    /**
     * Insertar una nueva unidad de medida en el catálogo
     */
    public static function crear_unidad_medida(
        string $nombre,
        string $abreviatura
    ): int {
        return UnidadMedida::insertGetId([
            'nombre' => $nombre,
            'abreviatura' => $abreviatura,
        ]);
    }

    /**
     * Verificar si ya existe una unidad de medida con ese nombre o abreviatura.
     * La comparación es case-insensitive para evitar duplicados visuales.
     *
     * @param array{
     *     nombre: ?string,
     *     abreviatura: ?string,
     *     excluir_id: ?int
     * } $criterios
     */
    public static function ya_existe(array $criterios): bool
    {
        $query = UnidadMedida::query();

        $nombre = $criterios['nombre'] ?? null;
        $abreviatura = $criterios['abreviatura'] ?? null;
        $excluir_id = $criterios['excluir_id'] ?? null;

        $query->where(function ($q) use ($nombre, $abreviatura) {
            if ($nombre !== null && $nombre !== '') {
                $q->orWhereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)]);
            }
            if ($abreviatura !== null && $abreviatura !== '') {
                $q->orWhereRaw('LOWER(abreviatura) = ?', [mb_strtolower($abreviatura)]);
            }
        });

        if ($excluir_id !== null) {
            $query->where('id', '!=', $excluir_id);
        }

        return $query->exists();
    }
}
