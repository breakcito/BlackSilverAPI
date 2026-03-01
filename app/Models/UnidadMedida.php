<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UnidadMedida extends Model
{
    protected $table = 'unidad_medida';
    public $timestamps = false;
    protected $fillable = [
        'nombre',
        'abreviatura',
        'tipo', // base | presentacion
    ];

    public static function get_unidades_medida()
    {
        $sql = '
        SELECT
            id AS id_unidad_medida,
            nombre,
            abreviatura
        FROM
            unidad_medida
        ORDER BY nombre ASC
        ';

        return DB::select($sql);
    }
}
