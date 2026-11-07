<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoComprobanteFleteDespacho extends Model
{
    protected $table = 'pago_comprobante_flete_despacho';

    public $timestamps = false;

    protected $fillable = [
        'id_comprobante_flete_despacho',
        'id_cuenta_bancaria_empresa',
        'id_empleado_registro',
        //
        'medio_pago', // enum: Transferencia / Depósito / Efectivo - solo guardar como varchar
        'numero_operacion', // Obligatorio si es transferencia o deposito
        'fecha_hora_pago',
        'es_para_detraccion',
        'observacion',
        'evidencias',
        'monto_pagado',
        'created_at',
    ];
}
