<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudReabastecimientoRecepcionDetalle extends Model
{
    protected $table = 'solicitud_reabastecimiento_recepcion_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_solicitud_reabastecimiento_recepcion',
        'id_solicitud_reabastecimiento_entrega_detalle',
        'cantidad_recepcionada_base',
        'estado',
    ];
}
