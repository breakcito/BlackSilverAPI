<?php

namespace App\Modules\Menu\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;


class Submodulo extends Model
{
    protected $table = "submodulo";
    public $timestamps = false;
    protected $fillable = [
        'id_modulo',
        'nombre',
        'path',
    ];

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
}
