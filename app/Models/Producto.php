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
        //
        'es_perecible',
        // bool que ayuda a saber si el producto es auditable para ocultarlo
        'es_auditable',
        //
        'stock_minimo_base',
        // es el costo promedio en soles que tiene el producto, este se 
        // va actualizando en base a los registros que se van 
        // teniendo en las ordenes de compra
        'costo_promedio_base',
        //
        'tiempo_espera_vencimiento',
        'periodo_espera_vencimiento',
        'dias_espera_vencimiento',
        //
        'estado',
    ];
}
