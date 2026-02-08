<?php

namespace App\Modules\Sistema\Infraestructure\Models;

use App\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Modelo que gestionara los modulos, submodulos y secciones del sistema
 */
class Menu extends Model
{
    public static function get_modulos_by_rol(int $id_rol)
    {
        $sql = '
        /*
        Obtener los modulos para el menu
        de navegacion
        */
        SELECT DISTINCT
            md.id AS id_modulo,
            md.nombre
        FROM
            modulo md
        INNER JOIN submodulo sb ON
            sb.id_modulo = md.id
        INNER JOIN seccion sc ON
            sc.id_submodulo = sb.id
        INNER JOIN seccion_rol scr ON
            scr.id_seccion = sc.id
        WHERE
            scr.id_rol = ?
        ORDER BY md.nombre;
        ';

        return DB::select($sql, [
            $id_rol,
        ]);
    }

    public static function get_submodulos_by_rol_and_modulo(int $id_rol, int $id_modulo): array
    {
        $sql = '
        /*
        Obtener los submodulos para el menu
        de navegacion
        */
        SELECT DISTINCT
            sb.id as id_submodulo,
            sb.nombre
        FROM
            submodulo sb
        INNER JOIN seccion sc ON
            sc.id_submodulo = sb.id
        INNER JOIN seccion_rol scr ON
            scr.id_seccion = sc.id
        WHERE
            scr.id_rol = ? AND 
            sb.id_modulo = ?
        ORDER BY sb.nombre;
        ';

        return DB::select($sql, [
            $id_rol,
            $id_modulo,
        ]);
    }

    public static function get_secciones_by_rol_and_submodulo(int $id_rol, int $id_submodulo): array
    {
        $sql = '
        /*
        Obtener las secciones para el menu
        de navegacion
        */
        SELECT DISTINCT
            sc.id AS id_seccion,
            sc.nombre,
            sc.url
        FROM
            seccion sc
        INNER JOIN seccion_rol scr ON
            scr.id_seccion = sc.id
        WHERE
            scr.id_rol = ? AND sc.id_submodulo = ?
        ORDER BY
            sc.nombre;
        ';

        return DB::select($sql, [
            $id_rol,
            $id_submodulo,
        ]);
    }
}
