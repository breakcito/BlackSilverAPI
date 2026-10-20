<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla que registra los zarandeos hechos de un tipo de carbon en el almacen. 
 * Al registrar el zarandeo, se actualiza el stock de ese tipo de carbon
 * zarandeado, haciendo una resta (stock actual - (suma de toda la cantidad de variantes extraidas))
 * pues si tenia 10TN de carbon 'Mixto' y zarandea 4TN que resultaron en otras
 * variantes entonces tengo 6TN de carbon 'Mixto' y 4TN de carbon distribuidos entre otros tipos
 * Cuando sea un rezarandeo, solo se actualizara el stock pero no se haran registros en el kardex
 * pues no esta saliendo ni ingresando nada del/al almacen 
 */
class ZarandeoCarbon extends Model
{
    protected $table = 'zarandeo_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_almacen', // en que almacen se realiza ese zarandeo
        'id_empleado_registro', // quien registra este zarandeo
        'id_empleado_supervisor', // quien fue el supervisor de planta que reporto estos resultados - opcional
        'id_tipo_carbon', // el tipo de carbon zarandeado
        //
        'calidad', // que calidad fue zarandeada
        // en toneladas, cuanto se esta zarandeando. Si este zarandeo viene de un grupo de cargas 
        // entonces la cantidad tamizada sera la suma del campo de 'cantidad' que representa 
        // la cantidad de toneladas que trajo cada uno de esos carros, pero se le permite al usuario modificar este valor
        // es opcional colocar este valor. Si no coloca nada o es 0, entonces no se actualiza el stock
        // del tipo de carbon zarandeado
        'cantidad_zarandeada',
        'cantidad_extraida', // la suma de toda la cantidad de variantes extraidas de este tipo de carbon
        //
        'es_rezarandeo', // si este tamizaje es del mismo stock que ya tiene la empresa, si no lo es es porque viene de un grupo de cargas
        //
        'fecha_hora_zarandeo',
        'evidencias',
        'created_at',
    ];
}
