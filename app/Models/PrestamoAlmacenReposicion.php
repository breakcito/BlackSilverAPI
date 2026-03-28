<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla que presenta las reposiciones que realiza logistica
 * a los almacenes que fueron prestamistas, con el fin
 * de reponer el stock entregado.
 */
class PrestamoAlmacenReposicion extends Model
{
    protected $table = 'prestamo_almacen_reposicion';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen', // el prestamo que se esta reponiendo
        'id_almacen_entrega', // uno de los almacenes principales
        'id_empleado_registro', // empleado que realiza/registra la reposicion
        'correlativo', // prefijo: RPS
        'numero_correlativo',
        'observacion',
        'fecha_hora_reposicion', // fecha y hora que el usuario fija en la ui
        'evidencias',
        'created_at', // fecha y hora de registro en el sistema
        'estado', // En Despacho / Recepcionado
    ];
}
