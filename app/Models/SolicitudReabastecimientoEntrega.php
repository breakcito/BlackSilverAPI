<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudReabastecimientoEntrega extends Model
{
    protected $table = 'solicitud_reabastecimiento_entrega';

    public $timestamps = false;

    protected $fillable = [
        'id_solicitud_reabastecimiento',
        'id_almacen_entrega', // un almacen principal
        'id_empleado_entrega',
        'id_empleado_recibe',
        'correlativo',
        'numero_correlativo',
        'fecha_hora_entrega',
        'observacion',
        'evidencias',
        'created_at',
        'estado',
    ];
}
