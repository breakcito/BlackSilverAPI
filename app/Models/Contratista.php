<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contratista extends Model
{
    protected $table = 'contratista';

    public $timestamps = false;

    protected $fillable = [
        'id_mina',
        'path_foto',
        'nombre',
        'apellido',
        'dni',
        'ruc',
        'carnet_extranjeria',
        'pasaporte',
        'fecha_nacimiento',
        'estado',
    ];
}
