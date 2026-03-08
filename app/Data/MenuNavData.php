<?php

namespace App\Data;

use Illuminate\Support\Facades\DB;

class MenuNavData
{
    /**
     * Obtener los modulos del sistema para el menu de navegacion
     */
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
            scr.id_rol = :id_rol AND
            md.estado = "Activo" AND
            sb.estado = "Activo" AND
            sc.estado = "Activo";
        ';

        return DB::select($sql, [
            'id_rol' => $id_rol,
        ]);
    }

    /**
     * Obtener los submodulos del sistema para el menu de navegacion
     */
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

    /**
     * Obtener las secciones del sistema para el menu de navegacion
     */
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
            scr.id_rol = :id_rol AND
            sc.id_submodulo = :id_submodulo
        ';

        return DB::select($sql, [
            'id_rol' => $id_rol,
            'id_submodulo' => $id_submodulo,
        ]);
    }
}
