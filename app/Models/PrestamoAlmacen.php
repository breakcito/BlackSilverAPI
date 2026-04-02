<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrestamoAlmacen extends Model
{
    protected $table = 'prestamo_almacen';

    public $timestamps = false;

    protected $fillable = [
        'id_solicitud_reabastecimiento',
        'id_almacen_solicitante',
        'id_almacen_prestamista',
        'id_empleado_registro',
        'correlativo',
        'numero_correlativo',
        'fecha_hora_prestamo',
        'fecha_limite_devolucion',
        'created_at',
        'estado',
    ];

    protected $casts = [
        'id_solicitud_reabastecimiento' => 'integer',
        'id_almacen_solicitante' => 'integer',
        'id_almacen_prestamista' => 'integer',
        'id_empleado_registro' => 'integer',
        'numero_correlativo' => 'integer',
        'fecha_hora_prestamo' => 'datetime',
        'fecha_limite_devolucion' => 'datetime',
        'created_at' => 'datetime',
    ];
}
