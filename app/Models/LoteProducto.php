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
        // referencias para saber de que compra proviene
        'id_orden_compra_recepcion_detalle', // de que recepcion de la compra proviene
        'id_orden_compra_detalle', // de que detalle de la compra proviene ya que podrian haberse pedido mas de 1 vez el mismo producto pero con precios diferentes
        //
        'tabla_origen', // el nombre de la tabla de donde provino el lote
        //
        'correlativo', // LOT-
        'numero_correlativo',
        'descripcion',
        //
        // Para saber de que compra provino en caso no haya venido desde el modulo de ordenes de compra
        'serie_factura_compra',// opcional
        'numero_factura_compra',// opcional
        //
        'stock_actual', // segun la unidad del lote
        'contenido_por_presentacion', // cuantas unidades del producto hay en una unidad del lote: Ej. 12KG x Saco
        'stock_actual_base', // segun la unidad base del producto
        //
        'costo_promedio_base', // el costo promedio del producto al momento del registro
        'costo_promedio_por_unidad', //  Cuanto cuesta una unidad del lote en base al costo promedio
        'costo_por_unidad', // Cuanto costó realmente una unidad del lote en la orden de compra de donde provino
        //
        'fecha_hora_ingreso',
        'fecha_vencimiento',
        //
        'created_at',
        'estado',
    ];
}
