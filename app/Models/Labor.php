<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Labor extends Model
{
    protected $table = 'labor';

    public $timestamps = false;

    protected $fillable = [
        'id_empresa',
        'id_mina',
        'id_tipo_labor',
        //
        'correlativo',
        'numero_correlativo',
        'nombre',
        'prefijo', // utilizado para concatenarlo en los correlativos de los lotes de mineral
        'descripcion',
        'tipo_sostenimiento',
        'veta',
        'ancho',
        'alto',
        'nivel',
        'fecha_inicio',
        'fecha_fin_estimada',
        'fecha_cierre',
        //
        'created_at',
        'estado',
    ];
}
