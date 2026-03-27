<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla que presenta CADA DETALLE/PRODUCTO de las reposiciones que
 * realiza logistica a los almacenes que fueron prestamistas,
 * con el fin de reponer el stock entregado.
 */
class PrestamoAlmacenReposicionDetalle extends Model
{
    protected $table = 'prestamo_almacen_reposicion_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen_reposicion', // la reposicion
        'id_prestamo_almacen_detalle', // el detalle/producto del prestamo que se esta reponiendo
        'id_lote_producto', // el lote del almacen principal elegido para reponer
        'cantidad_base', // la cantidad en base a la unidad de medida del producto
        'cantidad_lote', //     la cantidad en base a la unidad de medida del lote
        'cantidad_solicitud', // la cantidad en base a la unidad de medida de la solicitud
        'estado', // En Despacho / Recepcionado
    ];
}
