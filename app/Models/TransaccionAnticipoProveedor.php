<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo que hace referencia a los anticipos usados en una compra de carbon al aprobarse su liquidacion
 */
class TransaccionAnticipoProveedor extends Model
{
    protected $table = 'transaccion_anticipo_proveedor';

    public $timestamps = false;

    protected $fillable = [
        'id_anticipo_proveedor',
        'id_compra_carbon',
        'monto_retirado',
    ];
}
