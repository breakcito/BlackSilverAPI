<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuentaBancariaEmpresa extends Model
{
    protected $table = 'cuenta_bancaria_empresa';

    public $timestamps = false;

    protected $fillable = [
        'id_empresa',
        'id_banco',
        'moneda', // Soles / Dolares
        'numero_cuenta',
        'cci',
        'es_para_detraccion', // Disponible solo para el banco de la nacion
        'estado', // EstadoBase
    ];
}
