<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlmacenCarbonProveedor extends Model
{
    protected $table = 'almacen_carbon_proveedor';

    public $timestamps = false;

    protected $fillable = [
        'id_proveedor',
        'id_departamento',
        'id_provincia',
        'id_distrito',
        'direccion',
        'estado',
    ];
}
