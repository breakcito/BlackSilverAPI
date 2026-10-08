<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo que hace referencia a los anticipos usados en una 
 * compra de carbon
 */
class TransaccionAnticipoProveedor extends Model
{
    protected $table = 'transaccion_anticipo_proveedor';

    public $timestamps = false;

    protected $fillable = [
        'id_anticipo_proveedor',
        'id_compra_carbon',
        'id_comprobante_compra_carbon', // cuando la orden de compra aplica igv, el anticipo va asociado a un comprobante
        'id_pago_compra_carbon', // cuando  la orden de compra no aplica IGV, el anticipo va asociado a un pago
        'monto_retirado',
    ];
}
