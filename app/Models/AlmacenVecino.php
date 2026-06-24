<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// esta tabla es utilizada para asignar a los vecinos o almacenes mas cercanos a otro
class AlmacenVecino extends Model
{
    protected $table = 'almacen_vecino';

    public $timestamps = false;

    protected $fillable = [
        'id_almacen_a',
        'id_almacen_b',
    ];
}
