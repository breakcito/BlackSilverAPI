<?php

namespace App\Modules\Menu\Models;

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
            md.nombre,
            md.path
        FROM
            modulo md
        INNER JOIN submodulo sb ON
            sb.id_modulo = md.id
        INNER JOIN seccion sc ON
            sc.id_submodulo = sb.id
        INNER JOIN seccion_rol scr ON
            scr.id_seccion = sc.id
        WHERE
            scr.id_rol = :id_rol;
        ';

        return DB::select($sql, [
            'id_rol' => $id_rol,
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
            sb.nombre,
            sb.path
        FROM
            submodulo sb
        INNER JOIN seccion sc ON
            sc.id_submodulo = sb.id
        INNER JOIN seccion_rol scr ON
            scr.id_seccion = sc.id
        WHERE
            scr.id_rol = :id_rol AND 
            sb.id_modulo = :id_modulo;
        ';

        return DB::select($sql, [
            'id_rol' => $id_rol,
            'id_modulo' => $id_modulo,
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
            sc.path
        FROM
            seccion sc
        INNER JOIN seccion_rol scr ON
            scr.id_seccion = sc.id
        WHERE
            scr.id_rol = :id_rol AND sc.id_submodulo = :id_submodulo;
        ';

        return DB::select($sql, [
            'id_rol' => $id_rol,
            'id_submodulo' => $id_submodulo,
        ]);
    }

    public static function get_urls_vistas(): array
    {
        $sql = "
        /*
        Obtener todas las urls's de cada vista
        */
        SELECT DISTINCT
            CONCAT('/', md.path, '/', sb.path, '/', sc.path) as url_vistas
        FROM seccion sc
        INNER JOIN submodulo sb on sb.id = sc.id_submodulo
        INNER JOIN modulo md on md.id = sb.id_modulo;
        ";

        return DB::select($sql);
    }

    public static function get_urls_vistas_by_rol(int $id_rol): array
    {
        $sql = "
        /*
        Obtener todas las urls's de cada vista por rol
        */
        SELECT DISTINCT
            CONCAT('/', md.path, '/', sb.path, '/', sc.path) as url_vistas
        FROM seccion sc
        INNER JOIN submodulo sb on sb.id = sc.id_submodulo
        INNER JOIN modulo md on md.id = sb.id_modulo
        INNER JOIN seccion_rol sr on sr.id_seccion = sc.id
        WHERE sr.id_rol = :id_rol;
        ";

        return DB::select($sql, [
            'id_rol' => $id_rol,
        ]);
    }
}
