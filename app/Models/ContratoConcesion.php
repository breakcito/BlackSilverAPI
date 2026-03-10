<?php

namespace App\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ContratoConcesion extends Model
{
    protected $table = 'contrato_concesion';

    public $timestamps = false;

    protected $fillable = [
        'id_empresa',
        'id_concesion',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];
}
