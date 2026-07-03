<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empleado extends Model
{
    protected $table = 'empleado';

    public $timestamps = false;

    protected $fillable = [
        'id_cargo',
        'id_contrato_vigente',
        'id_mina',
        'qr_token',
        'nombre',
        'apellido',
        'dni',
        'genero',
        'ruc',
        'carnet_extranjeria',
        'pasaporte',
        'fecha_nacimiento',
        'con_contrato',
        'direccion',
        'telefono',
        'email',
        'url_foto',
        'es_contratista',
        'estado',
    ];

    protected $casts = [
        'es_contratista' => 'boolean',
        'con_contrato' => 'boolean',
        'fecha_nacimiento' => 'date',
        'id_contrato_vigente' => 'integer',
        'id_cargo' => 'integer',
        'id_mina' => 'integer',
    ];
}
