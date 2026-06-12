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
        'es_para_mina',
        'id_mina',
        'id_labor',
        'id_cliente',
        'tipo_carga',
        'id_tarifa',
        'cantidad_vueltas',
        'cantidad_sacos',
        'odometro_inicio',
        'odometro_fin',
        'observacion',
        'created_at'
    ];

    protected $casts = [
        'horometro_inicio' => 'decimal:2',
        'horometro_fin' => 'decimal:2',
        'odometro_inicio' => 'decimal:2',
        'odometro_fin' => 'decimal:2',
        'total_horas' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'costo_total' => 'decimal:2',
        'es_para_mina' => 'boolean',
        'cantidad_vueltas' => 'integer',
        'fecha_hora_inicio_control' => 'datetime',
        'fecha_hora_fin_control' => 'datetime',
        'created_at' => 'datetime',
    ];
}
