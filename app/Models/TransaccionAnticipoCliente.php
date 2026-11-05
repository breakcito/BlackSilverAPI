<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransaccionAnticipoCliente extends Model
{
    protected $table = 'transaccion_anticipo_cliente';

    public $timestamps = false;

    protected $fillable = [
        'id_anticipo_cliente',
        'id_comprobante_venta_carbon',
        'monto_retirado', // cuanto se uso del anticipo
    ];
}