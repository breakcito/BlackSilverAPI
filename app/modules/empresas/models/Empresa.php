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
     * Obtener todas las empresas.
     */
    public static function get_empresas()
    {
        $sql = '
        SELECT
            e.id,
            e.ruc,
            e.razon_social,
            e.nombre_comercial,
            e.abreviatura,
            e.path_logo
        FROM
            empresa e
        ORDER BY e.nombre_comercial
        ';

        return DB::select($sql);
    }
}
