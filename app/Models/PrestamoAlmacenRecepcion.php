<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo que representa a la tabla utilizada para registrar la RECEPCION de 
 * una entrega hecha por un PRESTAMO, es decir, una entrega hecha por el almacen
 * prestamista al almacen solicitante
 */
class PrestamoAlmacenRecepcion extends Model
{
    protected $table = 'prestamo_almacen_recepcion';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen_entrega',
        'id_empleado_registro',
        'observacion',
        'fecha_hora_recepcion',
        'evidencias',
        'con_incidencia',
        'created_at',
        'estado',
    ];

    protected $casts = [
        'evidencias' => 'array',
        'con_incidencia' => 'boolean',
    ];
}
