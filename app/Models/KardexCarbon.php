<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 */
class KardexCarbon extends Model
{
    protected $table = 'kardex_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_almacen', // en que almacen se dio esto
        'id_tipo_carbon', // saber que tipo de carbon acaba de ingresar/salir
        'id_carga_compra_carbon', // en que carga ingreso ese carbon
        'fecha_hora_movimiento', //  permite que sea retroactivo
        'tipo_movimiento', // Ingreso / Salida
        'stock_anterior',
        'cantidad_movimiento',
        'stock_resultante',
        'costo_total',
        'created_at',
    ];
}
