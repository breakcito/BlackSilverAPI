<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoVentaCarbon extends Model
{
    protected $table = 'distrito';

    public $timestamps = false;

    protected $fillable = [
        'id_empleado_registro', // 
        'id_comprobante_venta_carbon', // a que comprobante esta sujeto
        'id_cuenta_bancaria_empresa', // donde se recibe, opcional si es en efectivo
        //
        'medio_pago', // enum Transferencia / Depósito / Efectivo
        'numero_operacion', // Obligatorio si es transferencia o deposito
        'fecha_hora_pago', // 
        'es_para_detraccion', // bool - Si es asi, actualizara el campo de avance de pago de detraccion, sino, el del neto
        'monto_pagado', // 
        'observacion', // 
        'evidencias', // 
        'created_at', // 
    ];
}
