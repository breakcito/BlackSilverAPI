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
        'total', //  suma del descuento de flete de cada carga
        'con_detraccion', // bool - Dejarle a libertad del usuario esta eleccion
        'porcentaje_detraccion', // el porcentaje que sera aplicado para la detraccion
        'monto_detraccion', //  Se calcula a partir del total y el porcentaje de detraccion estipulado. Sera pagado mediante el banco de la nacion
        'total_neto', // total - monto de detraccion . es lo que se le pagara al transportista
        //
        'avance_pago_detraccion', // suma del monto en los pagos de detracccion. debe llegar a ser igual al monto de detraccion
        'avance_pago_neto', // suma del monto en los pagos netos, debe llegar al mismo monto de total_neto
        //
        'created_at',
        'estado' // enum: Pendiente de Pago / En proceso de Pago  / Pagado
    ];
}
