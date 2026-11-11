<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla que registra los gastos de produccion/procesamiento de carbon
 */
class GastoProduccionCarbon extends Model
{
    protected $table = 'gasto_produccion_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_empleado_registro', // en que almacen se dio esto
        //
        // o uno u otro
        'id_zarandeo_carbon', // saber en que zarandeo se hizo este gasto
        'id_blending_carbon', // saber en que blending se hizo este gasto
        // 
        'id_proveedor', // solo cuando es agua
        //
        'gasto', // enum TipoGastoZarandeo: Pala | Lanza | Agua | Escogedores | Otro
        'otro_gasto',  // solo cuando elige 'otro'
        'descripcion', // opcional
        'fecha', // la fecha efectuada de ese gasto
        'numero_reporte', // opcional
        // Solo se llena si es pala o lanza
        'hora_inicio', // a que hora empezo
        'hora_fin', // a que hora termino
        //
        'cantidad', // diferencia entre hora de fin e inicio cuando es pala o lanza
        'precio_unitario', // precio por hora
        'total', //  precio unitario * cantidad
        //
        'esta_pagado', // el gasto ya fue pagado al proveedor
        'fecha_pago', // cuando se pago (puede ser retroactiva)
        //
        'created_at', // cuando se registra en el sistema
    ];
}
