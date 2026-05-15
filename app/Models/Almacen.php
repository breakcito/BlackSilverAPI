<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Almacen extends Model
{
    protected $table = 'almacen';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
        'es_principal',
        'es_virtual', // true si es un almacen virtual, false si es un almacen fisico
        'estado',
    ];
}
