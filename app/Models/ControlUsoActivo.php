<?php

namespace App\Models;

use App\Shared\Enums\ControlUso\EstadoControlUso;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo que hace referencia al registro de control de uso de los activos fijos,
 * como horómetro u odómetro de inicio y fin, horas de trabajo, precio y costo total.
 */
class ControlUsoActivo extends Model
{
    protected $table = 'control_uso_activo';

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
        'id_lote_mineral',
        'id_cliente',
        'tipo_carga',
        'id_tarifa',
        'cantidad_vueltas',
        'cantidad_sacos',
        'odometro_inicio',
        'odometro_fin',
        'observacion',
        'tipo_turno',
        'uuid_grupo',
        'estado',
        'created_at'
    ];

    protected $casts = [
        'horometro_inicio' => 'decimal:2',
        'horometro_fin' => 'decimal:2',
        'odometro_inicio' => 'decimal:2',
        'odometro_fin' => 'decimal:2',
        // DECIMAL(13,6) en la columna; el cast conserva 6 decimales para no perder
        // precision al recuperar el valor. El display redondea via toLocaleString.
        'total_horas' => 'decimal:6',
        'precio_unitario' => 'decimal:6',
        'costo_total' => 'decimal:2',
        'es_para_mina' => 'boolean',
        'cantidad_vueltas' => 'integer',
        'fecha_hora_inicio_control' => 'datetime',
        'fecha_hora_fin_control' => 'datetime',
        'estado' => EstadoControlUso::class,
        'created_at' => 'datetime',
    ];
}
