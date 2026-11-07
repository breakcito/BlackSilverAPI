<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComprobanteMoliendaDespacho extends Model
{
    protected $table = 'comprobante_molienda_despacho';

    public $timestamps = false;

    protected $fillable = [
        'id_despacho_mineral',
        'id_empleado_registro',
        'codigo_comprobante',
        'fecha_emision',
        'con_detraccion',
        'porcentaje_detraccion', // 12%
        'porcentaje_igv',
        'subtotal',
        'igv',
        'total',
        'monto_detraccion',
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
        'subtotal' => 'decimal:2',
        'igv' => 'decimal:2',
        'total' => 'decimal:2',
        'monto_detraccion' => 'decimal:2',
        'neto_a_pagar' => 'decimal:2',
        'avance_detraccion' => 'decimal:2',
        'avance_neto' => 'decimal:2',
        'fecha_emision' => 'date',
        'created_at' => 'datetime',
    ];
}
