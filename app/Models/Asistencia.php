<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asistencia extends Model
{
    protected $table = 'asistencia';

    public $timestamps = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'id_empleado',
        'id_programacion_horario',
        'fecha_hora_ingreso',
        'minutos_tardanza',
        'fecha_hora_salida',
        'total_horas',
        'jornada_trabajada',
        'es_manual',
        'created_at',
    ];

    protected $casts = [
        'id_empleado' => 'integer',
        'id_programacion_horario' => 'integer',
        'fecha_hora_ingreso' => 'datetime',
        'minutos_tardanza' => 'integer',
        'fecha_hora_salida' => 'datetime',
        'total_horas' => 'decimal:2',
        'jornada_trabajada' => 'decimal:4',
        'es_manual' => 'boolean',
        'created_at' => 'datetime',
    ];
}
