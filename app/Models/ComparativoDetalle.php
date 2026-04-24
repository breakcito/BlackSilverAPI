<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComparativoDetalle extends Model
{
    protected $table = 'comparativo_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_comparativo',
        'id_producto',
        'id_solicitud_reabastecimiento_detalle'
    ];

    public static function crear_detalle(
        int $id_comparativo,
        int $id_producto,
        ?int $id_solicitud_detalle = null
    ): int {
        return self::insertGetId([
            'id_comparativo' => $id_comparativo,
            'id_producto' => $id_producto,
            'id_solicitud_reabastecimiento_detalle' => $id_solicitud_detalle
        ]);
    }
}
