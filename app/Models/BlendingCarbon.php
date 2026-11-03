<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla que registra los blendings de carbon.
 *
 * Un blending es un lote de carbon ya "mezclado": se obtiene tomando stock
 * de varias calidades del mismo tipo de carbon (ver `detalle_blending_carbon`)
 * y queda disponible como un producto propio, con su propio stock.
 */
class BlendingCarbon extends Model
{
    protected $table = 'blending_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_almacen',  // autoelegir el primer almacen de carbon
        'id_empleado_registro', // quien hizo el registro
        'id_empleado_supervisor', // supervisor del blending
        'id_tipo_carbon', // el tipo de carbon de este blending
        //
        'correlativo', // LT-<numero correlativo sin reseteo y sin 0>
        'numero_correlativo',
        //
        'porcentaje_ceniza', // lo digita el usuario, pues no hay forma de
        // estimar el porcentaje de ceniza resultante, alertar cuando supere
        // el 15% pero no ser bloqueante
        'stock_actual', // toneladas disponibles de este blending
        //
        'fecha_hora_blending', // cuando hicieron ese blending
        'evidencias', // json
        'created_at', // fecha/hora registro en el sistema
        'estado', // estado base: Activo/Eliminado
    ];
}
