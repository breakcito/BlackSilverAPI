<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submodulo extends Model
{
    protected $table = 'submodulo';
    public $timestamps = false;
    protected $fillable = [
        'id_modulo',
        'nombre',
        'path',
        'numero_orden', // va de 10 en 10, y ayuda a order los registro segun su flujo/importancia
        'estado',
    ];
}
