<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo que representa a la tabla utilizada para registrar los detalle de 
 * una RECEPCION de una entrega hecha por un PRESTAMO, es decir, una entrega
 * hecha por el almacen prestamista al almacen solicitante
 */
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
