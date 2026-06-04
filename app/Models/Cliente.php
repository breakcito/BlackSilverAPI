<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'cliente';

    public $timestamps = false;

    protected $fillable = [
        'tipo_entidad',
        'dni',
        'ruc',
        'razon_social',
        'direccion',
        'telefono',
        'correo',
        'estado',
        'created_at',
    ];
}
