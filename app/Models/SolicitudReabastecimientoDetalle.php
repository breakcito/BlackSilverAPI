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
        'id_unidad_medida', // bolsa
        'cantidad_solicitada',
        'cantidad_solicitada_base',
        'contenido_por_presentacion',
        'cantidad_entregada',
        'cantidad_entregada_base',
        'comentario',
        'comentario_decision',
        'estado',
    ];
}
