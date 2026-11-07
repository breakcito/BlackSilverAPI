<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DespachoMineral extends Model
{
    protected $table = 'despacho_mineral';

    public $timestamps = false;

    protected $fillable = [
        'id_lote_mineral',
        'id_molino',
        'id_empleado_registro',
        'fecha_despacho',
        'cantidad_sacos',
        'observaciones',
        'created_at',
        'estado',
    ];

    protected $casts = [
        'cantidad_sacos' => 'decimal:6',
        'fecha_despacho' => 'date',
        'created_at' => 'datetime',
    ];
}
