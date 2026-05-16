<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla que registra donde estuvo antes y a donde se fue un activo fijo
 */
class ActivoFijoUbicacionLog extends Model
{
    protected $table = 'activo_fijo_ubicacion_log';

    public $timestamps = false;

    protected $fillable = [
        'id_activo_fijo', // 
        'id_almacen', // opcional - en que almacen se encuentra almacenado este activo
        'id_mina', // opcional - en que mina se encuentra siendo usado este activo
        //
        'descripcion', // opcional
        //
        'tipo_movimiento', // enum MovimientoActivoFijo
        //
        'fecha_hora_movimiento', // fecha en la que se registro el movimiento del activo
        //
        'created_at', // fecha en la que se registro en el sistema
    ];
}
