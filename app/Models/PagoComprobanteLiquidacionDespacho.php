<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoComprobanteLiquidacionDespacho extends Model
{
    protected $table = 'pago_comprobante_liquidacion_despacho';

    public $timestamps = false;

    protected $fillable = [
        'id_comprobante_liquidacion_despacho',
        'id_cuenta_bancaria_empresa',
        'id_empleado_registro',
        //
        'medio_pago', // enum: Transferencia | Depósito | Efectivo
        'numero_operacion', // Obligatorio si es transferencia o deposito
        'fecha_hora_pago',
        'es_para_detraccion',
        'observacion',
        'evidencias',
        'monto_pagado',
        'created_at',
    ];

    protected $casts = [
        'es_para_detraccion' => 'boolean',
        'monto_pagado' => 'decimal:2',
        'fecha_hora_pago' => 'datetime',
        'created_at' => 'datetime',
    ];
}
