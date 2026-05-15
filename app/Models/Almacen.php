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
        'estado',
    ];
}
