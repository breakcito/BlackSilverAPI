<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Detalle de un blending: de que filas de `stock_carbon` se tomo el carbon.
 *
 * Una fila por (stock_carbon, cantidad tomada). `cantidad_tomada` es
 * REFERENCIAL: el usuario puede pedir mas de lo que hay en el stock y el
 * sistema lo permite (solo avisa). El descuento real del stock se aplica con
 * piso en cero, asi que puede quedar en 0.
 */
class DetalleBlendingCarbon extends Model
{
    protected $table = 'detalle_blending_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_blending_carbon', // el blending al que pertenece este detalle
        'id_stock_carbon', // el stock tomado segun el tipo de
        // calidad (Calidad, Baja, Intermedia, Alta)
        //
        'cantidad_tomada', // en toneladas, alertar si supera
        // el stock disponible de esa calidad pero permitir que
        // coloque lo que quiera pues todas son cantidades referenciales
    ];
}
