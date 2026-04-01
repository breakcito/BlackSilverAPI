<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudReabastecimientoRecepcion extends Model
{
    protected $table = 'solicitud_reabastecimiento_recepcion';

    public $timestamps = false;

    protected $fillable = [
        'id_solicitud_reabastecimiento_entrega',
        'id_empleado_registro',
        'observacion',
        'fecha_hora_recepcion',
        'evidencias',
        'con_incidencia',
        'created_at',
        'estado',
    ];

    protected $casts = [
        'evidencias' => 'array',
        'con_incidencia' => 'boolean',
    ];
}
