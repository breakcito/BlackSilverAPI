<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla que representa el stock de cada tipo de carbon
 * por almacen. Inicialmente la empresa solo tiene un almacen
 * pero se decidio separar esto por si en un futuro llegan a 
 * tener otro almacen. Solo habra un registro por tipo de carbon y almacen
 */
class StockCarbon extends Model
{
    protected $table = 'stock_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_almacen',
        'id_tipo_carbon',
        'stock_actual', // en toneladas, para saber cuanto hay
        'cambios_log', // JSON - para guardar cuando editan el peso
    ];
}
