<?php

namespace App\Modules\Empresas\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Modelo para la tabla empresa.
 */
class Empresa extends Model
{
    /**
     * Buscar empresa por ID.
     */
    public static function get_empresas_by_usuario(int $id_usuario)
    {
        $sql = '
        /*
        Obtener las empresas por usuario
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
            $id_usuario,
        ]);
    }
}
