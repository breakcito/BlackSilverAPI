<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Grupo de gastos de produccion de carbon.
 *
 * Un grupo agrupa varios zarandeos (o varios blendings) que pertenecen a la
 * misma operacion, para que los gastos de esa operacion (pala, lanza, agua,
 * escogedores) se registren una sola vez contra el grupo y no uno por
 * zarandeo o blending.
 *
 * Los grupos de zarandeo y los de blending son independientes entre si: cada
 * grupo pertenece a un solo tipo de operacion.
 */
class GrupoGastos extends Model
{
    protected $table = 'grupo_gastos';

    public $timestamps = false;

    protected $fillable = [
        'id_empleado_registro', // quien creo el grupo
        'tipo', // 'zarandeo' | 'blending'
        'created_at',
    ];
}
