<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequerimientoAlmacenEntregaDetalle extends Model
{
    protected $table = 'requerimiento_almacen_entrega_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_requerimiento_almacen_entrega',
        'id_requerimiento_almacen_detalle',
        'id_lote_producto', // si se entrego un lote: completo o parcial
        'id_activo_fijo', // si se entrego un activo
        'id_activo_fijo_destino', // vuelve a confirmar si lo que pide es para mantenimiento, debera especificar para que activo/maquina es
        'id_lote_mineral', // si es para produccion, debera indicar para que lote va
        //
        // O es uno u otro - aqui vuelve a confirmar si es para mantenimiento, sino es asi , es para produccion
        'para_mantenimiento', //  bool
        'para_produccion', // bool 
        //
        'cantidad_base',
        'cantidad_lote',
        'cantidad_requerimiento',
        //
        'costo_promedio_base',
        'costo_unidad_lote',
        'subtotal',
        //
        'created_at',
        'estado',
    ];
}
