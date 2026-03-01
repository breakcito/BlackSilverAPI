<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Modelo para la tabla empresa.
 */
class Empresa extends Model
{
    protected $table = 'empresa';

    public $timestamps = false;

    protected $fillable = [
        'ruc',
        'razon_social',
        'nombre_comercial',
        'abreviatura',
        'path_logo',
        'estado',
    ];

    /**
     * Obtener todas las empresas.
     */
    public static function get_empresas()
    {
        $sql = '
        SELECT
            e.id as id_empresa,
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

    /**
     * Buscar empresas asociadas a un usuario
     */
    public static function get_empresas_by_usuario(int $id_usuario)
    {
        $sql = '
        SELECT
            emp.id AS id_empresa,
            emp.ruc,
            emp.razon_social,
            emp.nombre_comercial,
            emp.abreviatura,
            emp.path_logo
        FROM
            empresa emp
        INNER JOIN usuario_empresa uem ON
            uem.id_empresa = emp.id
        WHERE
            uem.id_usuario = :id_usuario
        ';

        return DB::select($sql, [
            'id_usuario' => $id_usuario,
        ]);
    }
}
