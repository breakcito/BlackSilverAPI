<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ComprobanteVentaCarbon extends Model
{
    protected $table = 'comprobante_venta_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_empleado_registro', //
        'id_venta_carbon', //
        'id_liquidacion_venta_carbon', // de que liquidacion es
        //
        'codigo_comprobante', //
        'fecha_emision', //
        'observacion', //
        'evidencias', //
        //
        'subtotal', // es el mismo subtotal que la liquidacion
        'monto_pagado_anticipos', // suma del total de anticipos aplicados, no debe superar al subtotal
        //
        'base_imponible', // subtotal - monto_pagado_anticipos
        'monto_igv', // base_imponible * porcentaje_igv de la venta
        'importe_total', // base_imponible + monto_igv
        'monto_detraccion', // importe_total * porcentaje_detraccion de la venta
        'monto_neto', // importe_total - monto_detraccion
        'avance_pago_neto', // suma del monto en los pagos netos, debe llegar al mismo monto de monto_neto
        'avance_pago_detraccion', // suma del monto en los pagos de detracccion. debe llegar a ser igual al monto de detraccion
        //
        'created_at',
        'estado', // Pendiente de Pago / En proceso de Pago  / Pagado
    ];
}
