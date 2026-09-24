<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla encargada de alojar los lugares de donde cada proveedor extrae carbon
 */
class LugarExtraccionProveedor extends Model
{
    protected $table = 'lugar_extraccion_proveedor';
    public $timestamps = false;
    protected $fillable = [
        'id_proveedor',
        'id_lugar_extraccion_carbon',
    ];
}
