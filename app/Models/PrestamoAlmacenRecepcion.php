<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
