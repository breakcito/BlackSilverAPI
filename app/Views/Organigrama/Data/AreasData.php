<?php

namespace App\Views\Organigrama\Data;

use App\Models\Area;
use App\Shared\Enums\EstadoBase;
use Illuminate\Support\Facades\DB;

class AreasData
{
    /**
     * Listar o obtener un área
     */
    public static function get_areas(?int $id_area = null)
    {
        $sql = '
        SELECT
            a.id AS id_area,
            a.nombre,
            a.estado
        FROM
            area a
        WHERE
            1 = 1
        ';

        $params = [];
        if ($id_area !== null) {
            $sql .= ' AND a.id = :id_area';
            $params['id_area'] = $id_area;

            return DB::selectOne($sql, $params);
        }

        $sql .= ' AND a.estado = :estado ORDER BY a.nombre ASC';
        $params['estado'] = EstadoBase::Activo->value;

        return DB::select($sql, $params);
    }

    /**
     * Obtener área por ID
     */
    public static function get_area_by_id(int $id_area)
    {
        return self::get_areas(id_area: $id_area);
    }

    /**
     * Crear área
     */
    public static function crear_area(string $nombre)
    {
        return Area::insertGetId([
            'nombre' => $nombre,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Verificar duplicado
     */
    public static function verificar_nombre_duplicado(string $nombre)
    {
        return Area::where('nombre', $nombre)->exists();
    }
}
