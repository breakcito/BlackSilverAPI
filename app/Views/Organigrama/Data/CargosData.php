<?php

namespace App\Views\Organigrama\Data;

use App\Models\Cargo;
use App\Shared\Enums\EstadoBase;
use Illuminate\Support\Facades\DB;

class CargosData
{
    /**
     * Listar o obtener un cargo
     */
    public static function get_cargos(?int $id_area = null, ?int $id_cargo = null)
    {
        $sql = '
        SELECT
            c.id AS id_cargo,
            c.nombre,
            c.estado,
            c.id_area,
            a.nombre AS area_nombre
        FROM
            cargo c
        INNER JOIN area a ON a.id = c.id_area
        WHERE
            1 = 1
        ';

        $params = [];

        if ($id_area !== null) {
            $sql .= ' AND c.id_area = :id_area';
            $params['id_area'] = $id_area;
        }

        if ($id_cargo !== null) {
            $sql .= ' AND c.id = :id_cargo';
            $params['id_cargo'] = $id_cargo;

            return DB::selectOne($sql, $params);
        }

        $sql .= ' AND c.estado = :estado ORDER BY c.nombre ASC';
        $params['estado'] = EstadoBase::Activo->value;

        return DB::select($sql, $params);
    }

    /**
     * Obtener cargo por ID
     */
    public static function get_cargo_by_id(int $id_cargo)
    {
        return self::get_cargos(id_cargo: $id_cargo);
    }

    /**
     * Crear cargo
     */
    public static function crear_cargo(string $nombre, int $id_area)
    {
        return Cargo::insertGetId([
            'nombre' => $nombre,
            'id_area' => $id_area,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Verificar duplicado en la misma área
     */
    public static function verificar_nombre_duplicado(string $nombre, int $id_area)
    {
        return Cargo::where('nombre', $nombre)
            ->where('id_area', $id_area)
            ->exists();
    }
}
