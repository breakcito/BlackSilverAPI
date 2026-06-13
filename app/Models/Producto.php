<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'producto';

    public $timestamps = false;

    protected $fillable = [
        'id_categoria',
        'id_unidad_medida_base',
        //
        'nombre',
        'prefijo', // solo se requiere cuando es un activo fijo
        //
        'es_perecible',
        // bool que ayuda a saber si el producto es auditable para ocultarlo
        'es_auditable',
        // bool que ayuda a saber si el producto es usado para dar mantenimiento
        'para_mantenimiento',
        //
        'stock_minimo_base',
        // es el costo promedio en soles que tiene el producto, este se 
        // va actualizando en base a los registros que se van 
        // teniendo en las ordenes de compra
        'costo_promedio_base',
        'costo_promedio_base_log',
        //
        'tiempo_espera_vencimiento',
        'periodo_espera_vencimiento',
        'dias_espera_vencimiento',
        //
        'estado',
    ];

    protected $casts = [
        'costo_promedio_base_log' => 'array',
        'es_perecible' => 'boolean',
        'es_auditable' => 'boolean',
    ];
}
