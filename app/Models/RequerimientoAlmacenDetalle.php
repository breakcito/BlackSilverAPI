<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequerimientoAlmacenDetalle extends Model
{
    protected $table = 'requerimiento_almacen_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_requerimiento_almacen',
        'id_producto', // manzana - kilos
        'id_unidad_medida', // caja
        'id_empleado_atencion', // quien decide aprobar/rechazar el producto del requerimiento
        //
        'contenido_por_presentacion', // 10kg por caja
        'cantidad_solicitada', // 3 cajas
        'cantidad_solicitada_base', // 30kg
        'cantidad_entregada', // 2 cajas
        'cantidad_entregada_base', // 20kg
        'comentario',
        'comentario_decision', // luego de aprobar/rechazar, podran brindar algun comentario adicional
        //
        'estado',
    ];
}
