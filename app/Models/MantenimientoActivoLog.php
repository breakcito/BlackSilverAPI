<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MantenimientoActivoLog extends Model
{
    protected $table = 'mantenimiento_activo_log';

    public $timestamps = false;

    protected $casts = [
        'valor_control' => 'decimal:2',
        'fecha_hora_mantenimiento' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected $fillable = [
        'id_activo_fijo',
        'id_empleado_registro',
        'valor_control',
        'fecha_hora_mantenimiento',
        'tipo_control',
        'observacion',
        'created_at',
    ];
}
