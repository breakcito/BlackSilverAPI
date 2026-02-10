<?php

namespace App\Modules\Empresas\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Concesion extends Model
{

    // obtener la lista de concesiones y la empresa dueña de cada una
    public static function get_concesiones()
    {
        $sql = '
        /*
        Obtener las concesiones y la empresa de cada una
        */
        SELECT
            cn.id AS id_concesion,
            cn.id_empresa,
            emp.nombre_comercial as empresa,
            emp.path_logo as logo_empresa,
            cn.nombre,
            cn.estado
        FROM
            concesion cn
        INNER JOIN empresa emp ON
            emp.id = cn.id_empresa
        WHERE
            cn.estado = :estado
        ';

        return DB::select($sql, ['estado' => EstadoBase::Activo->value]);
    }

    // obtener las concesiones de una empresa
    public static function get_concesiones_by_empresa(int $id_empresa)
    {
        $sql = '
        /*
        Obtener las concesiones y la empresa de cada una
        */
        SELECT
            cn.id AS id_concesion,
            cn.nombre,
            cn.estado
        FROM
            concesion cn
        WHERE
            cn.id_empresa = :id_empresa AND
            cn.estado = :estado
        ';

        return DB::select($sql, [
            'id_empresa' => $id_empresa,
            'estado' => EstadoBase::Activo->value
        ]);
    }

    // crear una concesion
    public static function crear_concesion(int $id_empresa, string $nombre)
    {
        return DB::table('concesion')->insertGetId([
            'id_empresa' => $id_empresa,
            'nombre'     => $nombre,
            'estado'     => EstadoBase::Activo->value
        ]);
    }

    // verificar que no exista una concesion con el mismo nombre y empresa
    public static function verificar_concesion_existente(int $id_empresa, string $nombre)
    {
        $sql = '
        SELECT 
            COUNT(*) as count 
        FROM concesion con
        WHERE 
            con.id_empresa = :id_empresa AND 
            LOWER(con.nombre) = LOWER(:nombre)
        ';

        $result = DB::selectOne($sql, [
            'id_empresa' => $id_empresa,
            'nombre'     => $nombre
        ]);

        return $result->count > 0;
    }
    // actualizar una concesion
    public static function update_concesion(int $id, string $nombre)
    {
        return DB::table('concesion')
            ->where('id', $id)
            ->update([
                'nombre' => $nombre
            ]);
    }

    // eliminar (desactivar) una concesion
    public static function delete_concesion(int $id)
    {
        return DB::table('concesion')
            ->where('id', $id)
            ->update([
                'estado' => EstadoBase::Inactivo->value
            ]);
    }

    // obtener concesion por id
    public static function get_concesion_by_id(int $id)
    {
        $sql = '
        SELECT
            cn.id AS id_concesion,
            cn.id_empresa,
            emp.nombre_comercial as empresa,
            emp.path_logo as logo_empresa,
            cn.nombre,
            cn.estado
        FROM
            concesion cn
        INNER JOIN empresa emp ON
            emp.id = cn.id_empresa
        WHERE
            cn.id = :id AND
            cn.estado = :estado
        ';

        return DB::selectOne($sql, [
            'id' => $id,
            'estado' => EstadoBase::Activo->value
        ]);
    }
}
