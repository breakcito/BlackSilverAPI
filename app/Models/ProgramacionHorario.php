<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramacionHorario extends Model
{
    protected $table = 'programacion_horario';

    public $timestamps = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'id_empleado',
        'id_contrato_trabajo',
        'id_turno_laboral',
        'fecha_inicio',
        'por_tiempo_indefinido',
        'fecha_fin',
        'dias_laborables',
        'estado',
    ];

    protected $casts = [
        'id_empleado' => 'integer',
        'id_contrato_trabajo' => 'integer',
        'id_turno_laboral' => 'integer',
        'por_tiempo_indefinido' => 'boolean',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];
}
