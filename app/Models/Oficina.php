<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Oficina extends Model
{
    protected $table = 'oficina';
    public $timestamps = false;
    protected $fillable = [
        'id_empresa',
        //
        'nombre',
        'direccion',
        'es_principal',
        //
        'estado', // EstadoBase
    ];
}
