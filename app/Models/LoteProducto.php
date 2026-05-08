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
        'costo_promedio_base', // el costo promedio del producto al momento del registro
        'costo_por_unidad', // el costo en base a la unidad del lote
        //
        'fecha_hora_ingreso',
        'fecha_vencimiento',
        //
        'created_at',
        'estado',
    ];
}
