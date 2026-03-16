<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudReabastecimientoEntregaDetalle extends Model
{
    protected $table = 'reabastecimiento_entrega_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_reabastecimiento_entrega',
        'id_solicitud_reabastecimiento_detalle',
        'id_lote_producto',
        'cantidad_base',
        'cantidad_lote',
        'cantidad_solicitud',
        'created_at',
        'estado',
    ];
}
