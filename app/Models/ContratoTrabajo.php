<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContratoTrabajo extends Model
{
    protected $table = 'contrato_trabajo';

    public $timestamps = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'id_empleado',
        'id_cargo',
        'id_empresa',
        'id_almacen',
        'id_labor',
        'id_oficina',
        'tipo_contrato',
        'sueldo_base',
        'sueldo_real',
        'salario_diario',
        'fecha_inicio',
        'por_tiempo_indefinido',
        'evidencias',
        'fecha_fin',
        'duracion',
        'periodo_duracion',
        'duracion_dias',
        'fecha_fin_anticipada',
        'motivo_cierre',
        'cambios_log',
        'created_at',
        'estado',
    ];

    protected $casts = [
        'por_tiempo_indefinido' => 'boolean',
        'sueldo_base' => 'decimal:2',
        'sueldo_real' => 'decimal:2',
        'salario_diario' => 'decimal:2',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'fecha_fin_anticipada' => 'date',
        'evidencias' => 'array',
        'cambios_log' => 'array',
        'created_at' => 'datetime',
        'duracion' => 'integer',
        'duracion_dias' => 'integer',
    ];
}
