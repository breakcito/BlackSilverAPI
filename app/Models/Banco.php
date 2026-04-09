<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banco extends Model
{
    protected $table = 'banco';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'abreviatura',
        'estado', // Estado Basico
    ];
}
