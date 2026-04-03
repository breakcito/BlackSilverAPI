<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mina extends Model
{
    protected $table = 'mina';

    public $timestamps = false;

    protected $fillable = [
        'id_concesion',
        'nombre',
        'descripcion',
        'estado',
    ];
}
