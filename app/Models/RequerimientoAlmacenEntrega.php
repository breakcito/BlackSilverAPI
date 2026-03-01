<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequerimientoAlmacenEntrega extends Model
{
    protected $table = 'requerimiento_almacen_entrega'; // entrega_almacen

    public $timestamps = false;

    protected $fillable = [
        'id_requerimiento_almacen',
        'id_empleado_entrega', // quien entrega los productos
        'id_empleado_recibe', // quien recibe los productos
        //
        'correlativo',
        'numero_correlativo',
        'fecha_hora_entrega',
        'observacion',
        'evidencias', // json con el formato [{ "path": "...", "nombre": "...", "extension": "..." }]
        //
        'created_at',
        'estado',
    ];
}
