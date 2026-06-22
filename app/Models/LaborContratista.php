<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla que registra las labores asignadas a un contratista
 */
class LaborContratista extends Model
{
    protected $table = 'labor_contratista';

    public $timestamps = false;

    protected $fillable = [
        // guarda relacion con la tabla de 'empleado' debido a
        // que la tabla es reutilizada para registrar a los contratistas,
        // por si en algun momento se desea darle acceso al sistema a un minero
        'id_contratista',
        // id de la labor asignada
        'id_labor',
    ];
}
