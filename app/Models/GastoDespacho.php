<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GastoDespacho extends Model
{
    protected $table = 'gasto_despacho';

    public $timestamps = false;

    protected $fillable = [
        'id_despacho_mineral',
        'tipo_gasto', // enum: Winche | Winchadores
        'monto',
    ];
}
