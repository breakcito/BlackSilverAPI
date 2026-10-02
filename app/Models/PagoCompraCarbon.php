<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla que registra los pagos de una compra de carbon realizados 
 * al proveedor directamente o enlazados al comprobante que este entrego
 * si es que aplicó IGV o no
 */
class PagoCompraCarbon extends Model
{
    protected $table = 'pago_compra_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_compra_carbon',
        'id_comprobante_compra_carbon', // cuando el pago si este sujeto a un comprobante. cando la compra si aplique igv
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
