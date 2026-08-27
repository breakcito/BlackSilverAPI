<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoCarbon extends Model
{
    protected $table = 'tipo_carbon';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'codigo',
        'para_compra', // ayuda a saber los tipos de carbon que se compran
    ];
}