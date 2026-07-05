<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuentaBancariaEmpleado extends Model
{
    protected $table = 'cuenta_bancaria_empleado';

    public $timestamps = false;

    protected $fillable = [
        'id_empleado',
        'id_banco',
        'moneda',
        'numero_cuenta',
        'cci',
        'estado',
    ];
}
