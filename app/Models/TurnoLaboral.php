<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TurnoLaboral extends Model
{
    protected $table = 'turno_laboral';

    public $timestamps = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'tipo_turno',
        'hora_ingreso',
        'hora_salida',
        'minutos_tolerancia',
        'estado',
    ];

    protected $casts = [
        'minutos_tolerancia' => 'integer',
    ];
}
