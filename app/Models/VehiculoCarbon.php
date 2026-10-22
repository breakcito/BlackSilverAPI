<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo que registra los vehiculos usados en la venta de carbon
 * evita que el usuario digite a cada rato la marca y tipo de vehiculo
 */
class VehiculoCarbon extends Model
{
    protected $table = 'vehiculo_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_marca',
        'tipo_vehiculo',// enum: Tolva | Burrita
        'placa', 
    ];
}
