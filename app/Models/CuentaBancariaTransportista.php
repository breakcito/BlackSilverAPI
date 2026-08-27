<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuentaBancariaTransportista extends Model
{
    protected $table = 'cuenta_bancaria_transportista';

    public $timestamps = false;

    protected $fillable = [
        'id_transportista',
        'id_banco',
        'numero_cuenta',
        'cci',
        'estado', // Estado Basico
    ];
}
