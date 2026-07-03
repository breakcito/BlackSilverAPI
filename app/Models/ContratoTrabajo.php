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
        'salario_diario',
        'fecha_inicio',
        'por_tiempo_indefinido',
        'evidencias',
        'fecha_fin',
        'duracion',
        'periodo_duracion',
        'fecha_fin_anticipada',
        'created_at',
        'estado',
    ];

    protected $casts = [
        'por_tiempo_indefinido' => 'boolean',
        'sueldo_base' => 'decimal:2',
        'salario_diario' => 'decimal:2',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'fecha_fin_anticipada' => 'date',
        'evidencias' => 'array',
        'created_at' => 'datetime',
        'duracion' => 'integer',
    ];
}
