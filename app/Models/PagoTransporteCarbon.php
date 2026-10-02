<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla que registra los pagos del flete de una compra de carbon realizados 
 * al transportista
 */
class PagoTransporteCarbon extends Model
{
    protected $table = 'pago_transporte_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_comprobante_transporte_carbon', 
        'id_cuenta_bancaria_empresa', 
        'id_cuenta_bancaria_proveedor',
        'id_empleado_registro',
        'medio_pago', // Transferencia / Depósito / Efectivo - solo guardar como varchar
        'numero_operacion', // Obligatorio si es transferencia o deposito
        'fecha_hora_pago',
        'es_para_detraccion',
        'observacion',
        'evidencias',
        'monto_pagado',
        'created_at',
    ];
}
