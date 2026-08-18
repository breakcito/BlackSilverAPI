<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleCompraCarbon extends Model
{
    protected $table = 'detalle_compra_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_compra_carbon',
        'id_tipo_carbon',
        'cantidad',
        'precio_unitario',
        'subtotal',
    ];
}