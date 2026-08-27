<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla que representa la compra de carbon, agrupando
 * una o varias cargas/carros
 */
class CompraCarbon extends Model
{
    protected $table = 'compra_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_empresa', // la empresa que compra
        'id_proveedor', // el proveedor al que se le va a comprar
        'id_almacen', // en que lugar esta ingresando la carga de esta compra
        'id_empleado_registro', // quien registra
        'id_empleado_aprueba', // quien aprueba
        //
        // si NO aplica igv, el pago de esta compra se hará sin asociarlo a 
        // ningun comprobante (pago neto). Si es TRUE, entonces los pagos 
        // estaran asociados a un comprobante el cual posiblemente aplique detraccion
        'aplica_igv', // bool 
        //
        'porcentaje_igv', // 0 si no aplica igv, si si aplica por defecto 18 pero el usuario lo puede cambiar
        'correlativo',
        'numero_correlativo',
        'fecha_hora_ingreso',
        'fecha_hora_aprobacion',
        'evidencias',
        // montos
        'total_antes_descuento', // suma de subtotales antes de descuento
        'monto_igv', // 0 si aplica igv, sino, se calcula el monto en base al porcentaje de igv segun el total antes de descuento. por defecto todas las compras incluyen igv asi que solo se calcula el monto
        'descuento_flete', // suma del descuento aplicado por el flete. Esto es lo que pagara en total la empresa en flete, a uno o varios transportistas que realizaron este servicio
        'total_con_descuento', // suma de los subtotales con descuento aplicado. Esto es lo que le va a pagar al proveedor
        //
        'created_at',
        'estado',
    ];
}