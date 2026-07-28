<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContratoConcesion extends Model
{
    protected $table = 'contrato_concesion';

    public $timestamps = false;

    protected $fillable = [
        'id_empresa',
        'id_concesion',
        'fecha_inicio',
        'fecha_fin',
        'evidencias', // JSON - guardar evidencias del contrato entre la empresa y la concesión
        'estado',
    ];
}
