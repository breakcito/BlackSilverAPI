<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla utilizada para registrar a que labores haran uso/consumo de lo entregado 
 * en una entrega por un requerimiento de almacen
 */
class RequerimientoAlmacenEntregaDetalleConsumoLabor extends Model
{
    protected $table = 'requerimiento_almacen_entrega_detalle_consumo_labor';

    public $timestamps = false;

    protected $fillable = [
        'id_requerimiento_almacen_entrega_detalle_consumo',
        'id_labor',

    ];

}
