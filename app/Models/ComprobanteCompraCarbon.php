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
        'codigo_comprobante', // enum TipoComprobante
        'fecha_emision',
        'observacion',
        'evidencias',
        //
        'total', // es el total con descuento de la compra
        'con_detraccion', // bool - Dejarle a libertad del usuario esta eleccion
        'porcentaje_detraccion', // el porcentaje que sera aplicado para la detraccion
        'monto_detraccion', //  Se calcula a partir del total y el porcentaje de detraccion estipulado. Sera pagado mediante el banco de la nacion
        'total_neto', // total - monto de detraccion . es lo que se le pagara al proveedor
        'avance_pago_detraccion', // suma acumulada de los pagos de detraccion
        //
        'created_at',
        'estado' // enum: Pendiente de Pago / En proceso de Pago  / Pagado
    ];
}
