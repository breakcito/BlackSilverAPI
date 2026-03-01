<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    protected $table = 'categoria';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
        'tipo_requerimiento',
        'clasificacion_bien',
        'estado',
    ];
}
