<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComprobanteLiquidacionDespacho extends Model
{
    protected $table = 'comprobante_liquidacion_despacho';

    public $timestamps = false;

    protected $fillable = [
        'id_despacho_mineral',
        'id_liquidacion_mineral',
        'id_empleado_registro',
        'codigo_comprobante',
        'fecha_emision',
        'tipo_cambio', // 1 por defecto: el comprobante es en dolares
        'con_detraccion', // bool
        'porcentaje_detraccion', // 12%
        'porcentaje_igv',
        'subtotal',
        'igv',
        'total',
        'monto_detraccion',
        'monto_detraccion_soles', // monto_detraccion x tipo_cambio
        'neto_a_pagar',
        'avance_detraccion',
        'avance_neto',
        'evidencias',
        'created_at',
        'estado',
    ];

    protected $casts = [
        'con_detraccion' => 'boolean',
        'porcentaje_detraccion' => 'decimal:6',
        'porcentaje_igv' => 'decimal:6',
        'tipo_cambio' => 'decimal:6',
        'subtotal' => 'decimal:2',
        'igv' => 'decimal:2',
        'total' => 'decimal:2',
        'monto_detraccion' => 'decimal:2',
        'monto_detraccion_soles' => 'decimal:2',
        'neto_a_pagar' => 'decimal:2',
        'avance_detraccion' => 'decimal:2',
        'avance_neto' => 'decimal:2',
        'fecha_emision' => 'date',
        'created_at' => 'datetime',
    ];
}
