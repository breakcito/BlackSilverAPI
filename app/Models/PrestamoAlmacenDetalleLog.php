<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrestamoAlmacenDetalleLog extends Model
{
    protected $table = 'prestamo_almacen_detalle_log';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen_detalle',
        'id_empleado',
        'estado',
        'descripcion',
        'created_at',
    ];

    protected $casts = [
        'id_prestamo_almacen_detalle' => 'integer',
        'id_empleado' => 'integer',
        'created_at' => 'datetime',
    ];
}
