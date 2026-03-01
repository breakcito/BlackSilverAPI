<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoLabor extends Model
{
    protected $table = 'tipo_labor';

    public $timestamps = false;

    protected $fillable = [
        'prefijo',
        'nombre',
        'es_de_produccion',
    ];
}
