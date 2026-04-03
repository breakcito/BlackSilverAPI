<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Modulo extends Model
{
    protected $table = 'modulo';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'path',
        'numero_orden', // va de 10 en 10, y ayuda a order los registro segun su flujo/importancia
        'estado',
    ];
}
