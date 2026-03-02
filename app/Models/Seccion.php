<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Seccion extends Model
{
    protected $table = 'seccion';
    public $timestamps = false;
    protected $fillable = [
        'id_submodulo',
        'nombre',
        'path',
        'estado',
    ];

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
