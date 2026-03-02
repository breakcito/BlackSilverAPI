<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudReabastecimiento extends Model
{
    protected $table = 'solicitud_reabastecimiento';
    public $timestamps = false;
    protected $fillable = [
        'id_almacen_solicitante',
        'id_empleado_solicitante',
        //
        'correlativo',
        'numero_correlativo',
        'observacion',
        'fecha_hora_solicitud',
        //
        'created_at',
        'estado',
    ];
}
