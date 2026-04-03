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

    public static function get_unidades(
        ?int $id_unidad_medida = null,
        ?int $solo_base = null
    ) {
        $sql = '
        SELECT 
            id AS id_unidad_medida, 
            nombre, 
            abreviatura,
            es_base
        FROM unidad_medida
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_unidad_medida) {
            $sql .= " AND id = :id_unidad_medida";
            $params['id_unidad_medida'] = $id_unidad_medida;
            return DB::select($sql, $params);
        }

        if ($solo_base) {
            $sql .= " AND es_base = :solo_base";
            $params['solo_base'] = $solo_base;
        }

        $sql .= " ORDER BY nombre ASC";
        return DB::select('
            
        ');
    }
}
