<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla que registra los tamizajes hechos de un tipo de carbon en el almacen. 
 * Al registrar el tamizaje, se actualiza el stock de ese tipo de carbon
 * tamizado, haciendo una resta (stock actual - (suma de toda la cantidad de variantes extraidas))
 * pues si tenia 10TN de carbon 'Mixto' y tamice 4TN que resultaron en otras
 * variantes entonces tengo 6TN de carbon 'Mixto' y 4TN de carbon distribuidos entre otros tipos
 * Cuando sea un retamizaje, solo se actualizara el stock pero no se haran registros en el kardex
 * pues no esta saliendo ni ingresando nada del/al almacen 
 */
class TamizajeCarbon extends Model
{
    protected $table = 'tamizaje_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_almacen', // en que almacen se realiza ese tamizaje
        'id_empleado_registro', // quien registra este tamizaje
        'id_empleado_supervisor', // quien fue el supervisor de planta que reporto estos resultados - opcional
        'id_tipo_carbon', // el tipo de carbon tamizado
        'id_carga_compra_carbon', // si este tamizaje es de una carga de acaba de llegar
        //
        // en toneladas, cuanto se esta tamizando. Si este tamizaje viene de una carga que ha 
        // llegado, entonces la cantidad tamizada sera el campo de 'cantidad' que representa 
        // la cantidad de toneladas que trajo ese carrro, pero se le permite al usuario modificar este valor
        // es opcional colocar este valor. Si no coloca nada o es 0, entonces no se actualiza el stock
        // del tipo de carbon tamizado
        'cantidad_tamizada', 
        'cantidad_extraida', // la suma de toda la cantidad de variantes extraidas de este tipo de carbon
        //
        'es_retamizaje', // si este tamizaje es del mismo stock que ya tiene la empresa, si no lo es es porque viene de una carga
        //
        'fecha_hora_tamizaje',
        'evidencias',
        'created_at',
    ];
}
