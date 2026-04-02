<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrestamoAlmacenRecepcionDetalle extends Model
{
    protected $table = 'prestamo_almacen_recepcion_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen_recepcion',
        'id_prestamo_almacen_entrega_detalle',
        'cantidad_recepcionada_base',
        'estado',
    ];
}
