<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquidacionMineral extends Model
{
    protected $table = 'liquidacion_mineral';

    public $timestamps = false;

    protected $fillable = [
        'id_despacho_mineral',
        'id_cliente',
        'id_empleado_registro',
        //
        'codigo_liquidacion',
        'elemento_quimico', // enum: Oro | Plata
        'fecha_liquidacion',
        //
        'peso_humedo',
        'tara',
        'porcentaje_humedad',
        'peso_seco',
        'merma',
        'peso_neto',
        //
        'ley_elemento', // en oz
        'porcentaje_recuperacion',
        'maquila',
        'penalidad',
        //
        'valor_por_tonelada',
        //
        'porcentaje_igv',
        'porcentaje_detraccion',
        'subtotal',
        'igv',
        'total',
        'detraccion',
        'neto',
        //
        'observaciones',
        'created_at',
        'estado', // enum: Borrador | Aprobada | Pagada
    ];

    protected $casts = [
        'peso_humedo' => 'decimal:6',
        'tara' => 'decimal:6',
        'porcentaje_humedad' => 'decimal:6',
        'peso_seco' => 'decimal:6',
        'merma' => 'decimal:6',
        'peso_neto' => 'decimal:6',
        'ley_elemento' => 'decimal:6',
        'porcentaje_recuperacion' => 'decimal:6',
        'maquila' => 'decimal:2',
        'penalidad' => 'decimal:2',
        'valor_por_tonelada' => 'decimal:2',
        'porcentaje_igv' => 'decimal:6',
        'porcentaje_detraccion' => 'decimal:6',
        'subtotal' => 'decimal:2',
        'igv' => 'decimal:2',
        'total' => 'decimal:2',
        'detraccion' => 'decimal:2',
        'neto' => 'decimal:2',
        'fecha_liquidacion' => 'date',
        'created_at' => 'datetime',
    ];
}
