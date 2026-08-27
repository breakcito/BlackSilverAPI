<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transportista extends Model
{
    protected $table = 'transportista';

    public $timestamps = false;

    protected $fillable = [
        'id_tipo_carbon',
        'tipo_entidad', // natural / juridico
        'razon_social',
        'ruc',
        'dni',
        'telefono',
        'estado',
    ];
}
