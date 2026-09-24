<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlmacenCarbonCliente extends Model
{
    protected $table = 'almacen_carbon_cliente';

    public $timestamps = false;

    protected $fillable = [
        'id_cliente',
        'id_departamento',
        'id_provincia',
        'id_distrito',
        'direccion',
        'estado',
    ];
}
