<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Tabla que precisa qué labores estan involucradas en
// un requerimiento de almacen
class RequerimientoAlmacenLabor extends Model
{
    protected $table = 'requerimiento_almacen_labor';

    public $timestamps = false;

    protected $fillable = [
        'id_requerimiento',
        'id_labor',
    ];
}
