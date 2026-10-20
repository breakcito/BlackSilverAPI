<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla que registra las variantes/tipo de carbon que se extraen 
 * de un tipo de carbon y el porcentaje aproximado. Por ejemplo, del
 * 100% del tipo de carbon de 'Mixto' se 85% 'Cisco' y 15% 'Tipo A'
 * siendo estos porcentajes aproximados 
 */
class VarianteCarbon extends Model
{
    protected $table = 'variante_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_tipo_carbon',
        'id_tipo_variante',
        'porcentaje_extraccion', // valor de referencia de cuanta cantidad de esa variante se extrae el tipo de carbon cuando se zarandea 
    ];
}