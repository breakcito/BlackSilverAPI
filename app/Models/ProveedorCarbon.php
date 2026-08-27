<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla que registra los tipos de carbon que vende un proveedor
 */
class ProveedorCarbon extends Model
{
    protected $table = 'proveedor_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_proveedor',
        'id_tipo_carbon',
    ];
}