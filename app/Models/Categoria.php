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
        'es_consumible',
        'para_cocina',
        'para_mina',
        'estado',
    ];

    protected $casts = [
        'es_consumible' => 'boolean',
        'para_cocina' => 'boolean',
        'para_mina' => 'boolean',
    ];
}
