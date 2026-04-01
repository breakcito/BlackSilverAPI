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
        'es_base', // true | false
    ];

    public static function get_unidades_medida()
    {
        return DB::select('
            SELECT 
                id AS id_unidad_medida, 
                nombre, 
                abreviatura,
                es_base
            FROM unidad_medida
            ORDER BY nombre ASC
        ');
    }
}
