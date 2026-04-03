<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequerimientoAlmacen extends Model
{
    protected $table = 'requerimiento_almacen';

    public $timestamps = false;

    protected $fillable = [
        'id_empleado_solicitante',
        'id_mina',
        'id_almacen_destino',
        'correlativo',
        'numero_correlativo',
        'premura',
        'observacion',
        'fecha_entrega_requerida',
        'created_at',
        'estado',
    ];
}
