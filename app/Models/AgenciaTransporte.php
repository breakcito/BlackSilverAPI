<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// esta tabla registra todas las agencias de transporte
// con las que se trabaja para que realicen entregas

class AgenciaTransporte extends Model
{
    protected $table = 'agencia_transporte';

    public $timestamps = false;

    protected $fillable = [
        'razon_social',
    ];
}