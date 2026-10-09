<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla encargada de alojar los precios/tarifas al comprar carbon segun
 * el porcentaje de ceniza que tengan
 */
class TarifaCarbon extends Model
{
    protected $table = 'tarifa_carbon';
    public $timestamps = false;
    protected $fillable = [
        'id_tipo_carbon',
        'inicio_porcentaje_ceniza',
        'fin_porcentaje_ceniza',
        'precio_unitario',
        'estado',
    ];
}
