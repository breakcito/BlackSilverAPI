<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Modulo extends Model
{
    protected $table = 'modulo';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'path',
        'estado',
    ];

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
}
