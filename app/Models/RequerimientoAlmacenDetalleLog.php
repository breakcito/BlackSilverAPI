<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Tabla que registra la trazabilidad de cada producto de un requerimiento de almacen
class RequerimientoAlmacenDetalleLog extends Model
{
    protected $table = 'requerimiento_almacen_detalle_log';

    public $timestamps = false;

    protected $fillable = [
        'id_requerimiento_almacen_detalle',
        'id_empleado', // quien provoco el cambio
        //
        'tipo_origen', // Solicitud o Atencion
        'descripcion', // descripcion del cambio
        //
        'created_at',
        'estado', // pendiente, aprobado, etc
    ];
}
