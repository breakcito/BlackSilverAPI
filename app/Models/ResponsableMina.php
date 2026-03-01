<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResponsableMina extends Model
{
    protected $table = 'responsable_mina';
    public $timestamps = false;
    protected $fillable = [
        'id_mina',
        'id_usuario',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];
}
