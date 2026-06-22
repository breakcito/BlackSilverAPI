<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla que registra el historial de empleados que 
 * son/fueron responsables de una mina
 */
class ResponsableMina extends Model
{
    protected $table = 'responsable_mina';

    public $timestamps = false;

    protected $fillable = [
        'id_mina',
        'id_empleado', 
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];
}
