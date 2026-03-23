<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrestamoAlmacenEntregaDetalle extends Model
{
    protected $table = 'prestamo_almacen_entrega_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen_entrega',
        'id_prestamo_almacen_detalle',
        'id_lote_salida',
        'id_lote_ingreso',
        'cantidad',
        'estado',
    ];
}
