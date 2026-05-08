<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoteProducto extends Model
{
    protected $table = 'lote_producto';

    public $timestamps = false;

    protected $fillable = [
        'id_producto',
        'id_unidad_medida', // unidad de medida del lote
        'id_almacen', // a que almacen le pertenece este lote
        'id_origen', // el id del registro de donde provino el lote
        //
        'tabla_origen', // el nombre de la tabla de donde provino el lote
        //
        'correlativo', // LOT-
        'numero_correlativo',
        'descripcion',
        //
        'stock_actual', // segun la unidad del lote
        'contenido_por_presentacion', // cuantas unidades del producto hay en una unidad del lote: Ej. 12KG x Saco
        'stock_actual_base', // segun la unidad base del producto
        //
        'costo_por_unidad', // el costo en base a la unidad del lote
        'costo_por_unidad_base', // el costo en base a la unidad del producto
        'subtotal_inicial', // es el costo inicial del lote, pues su valor actual dependera del stock actual que tenga
        //
        'fecha_hora_ingreso',
        'fecha_vencimiento',
        //
        'created_at',
        'estado',
    ];
}
