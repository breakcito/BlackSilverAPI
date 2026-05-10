<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenCompraComprobanteRecepcion extends Model
{
    protected $table = 'orden_compra_comprobante_recepcion';

    public $timestamps = false;

    protected $fillable = [
        'id_orden_compra_recepcion',
        'id_orden_compra_comprobante',
    ];
}
