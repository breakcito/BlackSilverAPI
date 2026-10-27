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
        'subtotal', // es la suma del campo subtotal de las cargas
        'monto_pagado_anticipos', // suma del total de anticipos aplicados, no debe superar al subtotal
        'base_imponible', // subtotal - monto_pagado_anticipos
        'monto_igv', //  base_imponible * porcentaje_igv de la compra de carbon
        'importe_total',  // base_imponible + monto_igv
        'monto_detraccion', //   importe_total * porcentaje_detraccion de la compra
        'monto_neto', // importe_total - monto_detraccion
        //
        'avance_pago_detraccion', // suma del monto en los pagos de detracccion. debe llegar a ser igual al monto de detraccion
        'avance_pago_neto', // suma del monto en los pagos netos, debe llegar al mismo monto de monto_neto
        //
        'created_at',
        'estado' // enum: Pendiente de Pago / En proceso de Pago  / Pagado
    ];
}
