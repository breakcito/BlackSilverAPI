<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProveedorCarbon extends Model
{
    protected $table = 'proveedor_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_proveedor',
        'id_tipo_carbon',
    ];
}