<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Marcaje extends Model
{
    protected $table = 'marcaje';

    public $timestamps = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'id_asistencia',
        'id_empleado',
        'id_programacion_horario',
        'id_empleado_registro',
        'tipo_marcaje',
        'fecha_hora',
        'evidencias',
        'es_manual',
        'qr_leido',
        'proceso_confirmado',
        'created_at',
    ];

    protected $casts = [
        'id_asistencia' => 'integer',
        'id_empleado' => 'integer',
        'id_programacion_horario' => 'integer',
        'id_empleado_registro' => 'integer',
        'fecha_hora' => 'datetime',
        'evidencias' => 'array',
        'es_manual' => 'boolean',
        'qr_leido' => 'boolean',
        'proceso_confirmado' => 'boolean',
        'created_at' => 'datetime',
    ];
}
