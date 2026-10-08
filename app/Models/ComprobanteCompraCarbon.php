<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Representa el comprobante de la compra de carbon la cual 
 * solo es registrada si en la compra se dijo que aplicaba igv
 */
class ComprobanteCompraCarbon extends Model
{
    protected $table = 'comprobante_compra_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_empleado_registro',
        'id_compra_carbon',
        //
        'codigo_comprobante', // el codigo/numero de la factura
        'fecha_emision',
        'observacion',
        'evidencias',
        //
        'total', // es la suma del campo subtotal_con_descuento de las cargas seleccionadas
        'con_detraccion', // bool - Dejarle a libertad del usuario esta eleccion
        'porcentaje_detraccion', // el porcentaje que sera aplicado para la detraccion
        'monto_detraccion', //  Se calcula a partir del total y el porcentaje de detraccion estipulado. Sera pagado mediante el banco de la nacion
        'total_sin_detraccion', // total - monto de detraccion
        'monto_pagado_anticipos', // suma del total de anticipos aplicados, no debe superar al total_sin_detraccion
        'total_neto', // total_sin_detraccion - el monto pagado en anticipos. es lo que se le pagara al proveedor
        //
        'avance_pago_detraccion', // suma del monto en los pagos de detracccion. debe llegar a ser igual al monto de detraccion
        'avance_pago_neto', // suma del monto en los pagos netos, debe llegar al mismo monto de total_neto
        //
        'created_at',
        'estado' // enum: Pendiente de Pago / En proceso de Pago  / Pagado
    ];
}
