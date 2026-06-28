<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo que hace referencia a la tabla de tarifas de uso por activo fijo.
 */
class TarifaUsoActivo extends Model
{
    protected $table = 'tarifa_uso_activo';

    public $timestamps = false;

    protected $fillable = [
        'id_activo_fijo',
        'tipo_control',
        'precio_unitario',
        'descripcion',
        'id_tipo_material',
        'distancia_metros',
        'created_at'
    ];

    protected $casts = [
        'precio_unitario' => 'decimal:2',
        'created_at' => 'datetime',
    ];
}
