<?php

namespace App\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Concesion extends Model
{
    protected $table = 'concesion';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'codigo_concesion',
        'codigo_reinfo',
        'ubigeo', // coordenadas
        'tipo_mineral', // TipoMineral
        'estado',
    ];
}
