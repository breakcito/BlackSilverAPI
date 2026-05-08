<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoteProducto extends Model
{
    protected $table = 'lote_producto';

    public $timestamps = false;

    protected $fillable = [
        'id_producto',
        'id_unidad_medida',
        'id_almacen',
        'descripcion',
        'correlativo',
        'numero_correlativo',
        'stock_actual',
        'contenido_por_presentacion',
        'costo_base', // el costo en base a la unidad base
        'stock_actual_base',
        'fecha_hora_ingreso',
        'fecha_vencimiento',
        'created_at',
        'estado',
    ];
}
