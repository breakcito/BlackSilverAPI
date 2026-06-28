<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoteMineral extends Model
{
    protected $table = 'lote_mineral';

    public $timestamps = false;

    protected $fillable = [
        'id_contratista',
        'id_mina',
        'id_labor',
        'id_empleado_registro',
        //
        'codigo',
        'descripcion',
        //
        'created_at',
        'estado',
    ];
}
