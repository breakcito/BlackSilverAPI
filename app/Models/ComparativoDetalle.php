<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComparativoDetalle extends Model
{
    protected $table = 'comparativo_detalle';
    
    public $timestamps = false;

    protected $fillable = [
        'id_comparativo',
        'id_producto',
        'id_solicitud_reabastecimiento_detalle'
    ];
}
