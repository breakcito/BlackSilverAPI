<?php

namespace App\Modules\Empresas\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Labor extends Model
{
    // obtener labores, opcionalmente filtrar por concesion
    public static function get_labores(?int $id_concesion = null)
    {
        $sql = '
        SELECT
            l.id as id_labor,
            l.id_concesion,
            c.nombre as concesion,
            l.nombre,
            l.descripcion,
            l.tipo_labor,
            l.tipo_sostenimiento,
            l.estado
        FROM
            labor l
        INNER JOIN concesion c ON c.id = l.id_concesion
        WHERE
            l.estado = :estado
        ';
        
        $params = ['estado' => EstadoBase::Activo->value];

        if ($id_concesion) {
            $sql .= ' AND l.id_concesion = :id_concesion';
            $params['id_concesion'] = $id_concesion;
        }

        return DB::select($sql, $params);
    }

    public static function get_labor_by_id(int $id)
    {
        $sql = '
        SELECT
            l.id as id_labor,
            l.id_concesion,
            c.nombre as concesion,
            l.nombre,
            l.descripcion,
            l.tipo_labor,
            l.tipo_sostenimiento,
            l.estado
        FROM
            labor l
        INNER JOIN concesion c ON c.id = l.id_concesion
        WHERE
            l.id = :id
        ';

        return DB::selectOne($sql, ['id' => $id]);
    }

    public static function crear_labor(int $id_concesion, string $nombre, ?string $descripcion, string $tipo_labor, string $tipo_sostenimiento)
    {
        return DB::table('labor')->insertGetId([
            'id_concesion' => $id_concesion,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'tipo_labor' => $tipo_labor,
            'tipo_sostenimiento' => $tipo_sostenimiento,
            'estado' => EstadoBase::Activo->value
        ]);
    }

    public static function update_labor(int $id, int $id_concesion, string $nombre, ?string $descripcion, string $tipo_labor, string $tipo_sostenimiento)
    {
        return DB::table('labor')
            ->where('id', $id)
            ->update([
                'id_concesion' => $id_concesion,
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'tipo_labor' => $tipo_labor,
                'tipo_sostenimiento' => $tipo_sostenimiento
            ]);
    }

    public static function delete_labor(int $id)
    {
        return DB::table('labor')
            ->where('id', $id)
            ->update([
                'estado' => EstadoBase::Inactivo->value
            ]);
    }
}
