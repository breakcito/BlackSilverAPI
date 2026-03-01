<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

// Tabla que precisa qué labores estan involucradas en
// un requerimiento de almacen
class RequerimientoAlmacenLabor extends Model
{
    protected $table = 'requerimiento_almacen_labor';

    public $timestamps = false;

    protected $fillable = [
        'id_requerimiento',
        'id_labor',
    ];

    public static function get_labores_por_requerimiento(int $id_requerimiento)
    {
        $sql = '
        SELECT
            l.id,
            l.nombre
        FROM
            requerimiento_almacen_labor ral
        INNER JOIN labor l ON l.id = ral.id_labor
        WHERE
            ral.id_requerimiento = :id_requerimiento
        ';

        return DB::select($sql, ['id_requerimiento' => $id_requerimiento]);
    }
}
