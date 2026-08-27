<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla encargada de alojar los lugares de donde cada proveedor extrae carbon
 */
class LugarExtraccionCarbon extends Model
{
    protected $table = 'lugar_extraccion_carbon';
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
