<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompraCarbon extends Model
{
    protected $table = 'compra_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_empresa',
        'id_proveedor',
        'id_empleado_registro',
        'id_empleado_aprueba',
        'porcentaje_igv',
        'correlativo',
        'numero_correlativo',
        'fecha_hora_compra',
        'fecha_hora_aprobacion',
        'evidencias_aprobacion',
        'total',
        'created_at',
        'estado',
    ];
}