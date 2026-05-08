<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KardexProducto extends Model
{
    protected $table = 'kardex_producto';

    public $timestamps = false;

    protected $fillable = [
        'id_lote_producto',
        'id_origen', // el registro por el cual se hizo el movimiento
        'tipo_movimiento', // Ingreso | Salida
        'tipo_origen', // titulo breve (Nuevo lote, recepcion, entrega, etc)
        'descripcion', // descripcion breve del movimiento relacionada al tipo de origen
        'stock_anterior',
        'stock_anterior_base',  // cuando habia antes en base a la unidad de medida del producto
        'cantidad_movimiento',
        'cantidad_movimiento_base', // cuando se saco/ingreso en base a la unidad de medida del producto
        'stock_resultante',
        'stock_resultante_base', // cuanto hay ahora en base a la unidad de medida del producto
        'costo_promedio_base', // cuanto costaba en promedio el producto del lote en el momento del movimiento
        'created_at', // cuando se registro el movimiento
    ];
}
