<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudReabastecimientoDetalle extends Model
{
    protected $table = 'solicitud_reabastecimiento_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_solicitud_reabastecimiento',
        'id_producto',
        'id_empleado_atencion', // quien aprueba o rechaza
        'id_unidad_medida_presentacion', // bolsa
        'cantidad_solicitada',
        'cantidad_solicitada_base',
        'cantidad_entregada',
        'cantidad_entregada_base',
        'cantidad_devuelta',
        'cantidad_devuelta_base',
        'comentario',
        'comentario_rechazo',
        'estado',
    ];
}
