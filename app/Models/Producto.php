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
        'nombre',
        'es_perecible',
        'es_auditable', // bool que ayuda a saber si los productos de esa categoria con auditables para ocultarlos
        'stock_minimo',
        'tiempo_espera_vencimiento',
        'periodo_espera_vencimiento',
        'dias_espera_vencimiento',
        'estado',
    ];
}
