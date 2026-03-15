<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Tabla que registra la trazabilidad de cada producto de una solicitud de reabastecimiento
class SolicitudReabastecimientoDetalleLog extends Model
{
    protected $table = 'solicitud_reabastecimiento_detalle_log';

    public $timestamps = false;

    protected $fillable = [
        'id_solicitud_reabastecimiento_detalle',
        'id_empleado', // quien provoco el cambio
        //
        'descripcion', // descripcion del cambio
        //
        'created_at',
        'estado', // pendiente, aprobado, etc
    ];
}
