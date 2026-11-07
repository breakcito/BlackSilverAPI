<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GastoDespacho extends Model
{
    protected $table = 'gasto_despacho';

    public $timestamps = false;

    protected $fillable = [
        'id_despacho_mineral',
        'id_empleado_registro',
        'tipo_gasto', // enum: Winche | Winchadores
        'descripcion',
        'monto',
        'created_at',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'created_at' => 'datetime',
    ];
}
