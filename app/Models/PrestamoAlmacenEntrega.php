<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrestamoAlmacenEntrega extends Model
{
    protected $table = 'prestamo_almacen_entrega';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen',
        'id_empleado_entrega',
        'id_empleado_recibe',
        'correlativo',
        'numero_correlativo',
        'fecha_hora_entrega',
        'observacion',
        'evidencias',
        'created_at',
        'estado',
    ];
}
