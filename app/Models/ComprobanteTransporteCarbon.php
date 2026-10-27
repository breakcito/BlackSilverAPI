<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Representa el comprobante del flete de una o varias cargas de una
 * compra de carbon, al indicarse que esas N cargas van a ser pagadas 
 * por la empresa y por tanto descontada al proveedor
 */
class ComprobanteTransporteCarbon extends Model
{
    protected $table = 'comprobante_transporte_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_compra_carbon',
        'id_empleado_registro',
        'id_transportista',
        //
        'codigo_comprobante', // el numero de la factura que entrega la empres de transportes
        'fecha_emision',
        'observacion',
        'evidencias',
        //
        'subtotal', // suma del campo flete_subtotal de las cargas seleccionadas
        'monto_igv', //   subtotal * porcentaje_igv_flete de la compra
        'importe_total', // subtotal + monto_igv
        'monto_detraccion', // importe_total * porcentaje_detraccion_flete de la compra
        'monto_neto', // importe_total - monto_detraccion
        //
        'avance_pago_detraccion', // ssuma del monto en los pagos de detracccion. debe llegar a ser igual al monto de detraccion
        'avance_pago_neto', // suma del monto en los pagos netos, debe llegar al mismo monto de monto_neto
        //
        'created_at',
        'estado' // enum: Pendiente de Pago / En proceso de Pago  / Pagado
    ];
}
