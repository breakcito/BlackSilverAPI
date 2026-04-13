<?php

namespace App\Data;

use Illuminate\Support\Facades\DB;

class MenuNavData
{
    /**
     * Obtener los modulos del sistema para el menu de navegacion
     */
    public static function get_menus_by_rol(int $id_rol): array
    {
        $sql = '
        SELECT DISTINCT
            mn.id AS id_menu,
            mn.nombre,
            mn.path,
            mn.numero_orden
        FROM menu mn
        INNER JOIN submenu sb ON sb.id_menu = mn.id
        INNER JOIN modulo md ON md.id_submenu = sb.id
        INNER JOIN modulo_rol mr ON mr.id_modulo = md.id
        WHERE
            mr.id_rol = :id_rol AND
            mn.estado = "Activo"
        ORDER BY mn.numero_orden ASC;
        ';

        return DB::select($sql, ['id_rol' => $id_rol]);
    }

    /**
     * Obtener los submodulos filtrados por múltiples IDs de módulos
     */
    public static function get_submodulos_by_rol_and_modulos(int $id_rol, array $ids_modulo): array
    {
        if (empty($ids_modulo)) return [];

        $placeholders = implode(',', array_fill(0, count($ids_modulo), '?'));

        $sql = "
        SELECT DISTINCT
            sb.id as id_submodulo,
            sb.id_modulo,
            sb.nombre,
            sb.path,
            sb.numero_orden
        FROM submodulo sb
        INNER JOIN seccion sc ON sc.id_submodulo = sb.id
        INNER JOIN seccion_rol scr ON scr.id_seccion = sc.id
        WHERE
            scr.id_rol = ? AND 
            sb.id_modulo IN ($placeholders) AND
            sb.estado = 'Activo' AND
            sc.estado = 'Activo'
        ORDER BY sb.numero_orden ASC;
        ";

        return DB::select($sql, array_merge([$id_rol], $ids_modulo));
    }

    /**
     * Obtener las secciones filtradas por múltiples IDs de submódulos
     */
    public static function get_secciones_by_rol_and_submodulos(int $id_rol, array $ids_submodulo): array
    {
        if (empty($ids_submodulo)) return [];

        $placeholders = implode(',', array_fill(0, count($ids_submodulo), '?'));

        $sql = "
        SELECT DISTINCT
            sc.id AS id_seccion,
            sc.id_submodulo,
            sc.nombre,
            sc.path,
            sc.numero_orden
        FROM seccion sc
        INNER JOIN seccion_rol scr ON scr.id_seccion = sc.id
        WHERE
            scr.id_rol = ? AND
            sc.id_submodulo IN ($placeholders) AND
            sc.estado = 'Activo'
        ORDER BY sc.numero_orden ASC;
        ";

        return DB::select($sql, array_merge([$id_rol], $ids_submodulo));
    }
}
