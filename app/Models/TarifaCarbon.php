<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla encargada de alojar los precios/tarifas al comprar carbon segun
 * el porcentaje de ceniza que tengan
 */
class TarifaCarbon extends Model
{
    protected $table = 'tarifa_carbon';
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
