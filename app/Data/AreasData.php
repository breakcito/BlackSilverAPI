<?php

namespace App\Data;

use App\Models\Area;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class AreasData
{
    /**
     * Metodo generico para obtener las areas de la empresa
     */
    public static function get_areas(
        ?int $id_area = null,
        ?EstadoBase $estado = null,
    ) {
        $sql = '
        SELECT
            ar.id AS id_area,
            ar.nombre,
            ar.estado
        FROM area ar
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_area !== null) {
            $sql .= " AND ar.id = :id_area";
            $params['id_area'] = $id_area;
            return DB::selectOne($sql, $params);
        }

        if ($estado !== null) {
            $sql .= " AND ar.estado = :estado";
            $params['estado'] = $estado->value;
        }

        $sql .= " ORDER BY ar.nombre;";
        return DB::select($sql, $params);
    }

    /**
     * Crear área
     */
    public static function crear_area(string $nombre): int
    {
        return Area::insertGetId([
            'nombre' => $nombre,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Verificar duplicado
     */
    public static function verificar_nombre_duplicado(string $nombre): bool
    {
        return Area::where('nombre', $nombre)->exists();
    }
}
