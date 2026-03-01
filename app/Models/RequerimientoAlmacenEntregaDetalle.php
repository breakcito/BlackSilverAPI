<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequerimientoAlmacenEntregaDetalle extends Model
{
    protected $table = 'requerimiento_almacen_entrega_detalle'; // entrega_almacen_detalle

    public $timestamps = false;

    protected $fillable = [
        'id_requerimiento_almacen_entrega',
        'id_requerimiento_almacen_detalle',
        'id_lote',
        'cantidad', // 2 cajas
        'cantidad_base', // 20kg
    ];
}
