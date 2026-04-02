<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrestamoAlmacenDetalle extends Model
{
    protected $table = 'prestamo_almacen_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen',
        'id_solicitud_reabastecimiento_detalle',
        'id_producto',
        'id_unidad_medida',
        'contenido_por_presentacion',
        'cantidad_solicitada',
        'cantidad_solicitada_base',
        'cantidad_prestada',
        'cantidad_prestada_base',
        'cantidad_repuesta',
        'cantidad_repuesta_base',
        'comentario',
        'estado',
    ];

    protected $casts = [
        'id_prestamo_almacen' => 'integer',
        'id_solicitud_reabastecimiento_detalle' => 'integer',
        'id_producto' => 'integer',
        'id_unidad_medida' => 'integer',
        'contenido_por_presentacion' => 'float',
        'cantidad_solicitada' => 'float',
        'cantidad_solicitada_base' => 'float',
        'cantidad_prestada' => 'float',
        'cantidad_prestada_base' => 'float',
        'cantidad_repuesta' => 'float',
        'cantidad_repuesta_base' => 'float',
    ];
}
