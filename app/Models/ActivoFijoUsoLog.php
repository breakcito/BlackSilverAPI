<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo que hace referencia al registro de control de uso de los activos fijos,
 * como horómetro o odómetro de inicio y fin, horas de trabajo, precio y costo total.
 */
class ActivoFijoUsoLog extends Model
{
    protected $table = 'activo_fijo_uso_log';

    public $timestamps = false;

    protected $fillable = [
        'id_activo_fijo',
        'fecha_hora_inicio_control',
        'fecha_hora_fin_control',
        'horometro_inicio',
        'horometro_fin',
        'total_horas',
        'precio_unitario',
        'costo_total',
        'observacion',
        'created_at'
    ];

    protected $casts = [
        'horometro_inicio' => 'decimal:2',
        'horometro_fin' => 'decimal:2',
        'total_horas' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'costo_total' => 'decimal:2',
        'fecha_hora_inicio_control' => 'datetime',
        'fecha_hora_fin_control' => 'datetime',
        'created_at' => 'datetime',
    ];
}
