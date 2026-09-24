<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnticipoProveedor extends Model
{
    protected $table = 'anticipo_proveedor
';

    public $timestamps = false;

    protected $fillable = [
        'id_empresa',
        'id_proveedor',
        'id_empleado_registro', // empleado que registra
        'id_empleado_anulacion', // el empleado que anula
        'id_cuenta_bancaria_empresa', // de que cuenta de Cupper salio el dinero. Obligatorio si es transferencia o deposito
        'medio_pago', //  Transferencia / Depósito / Efectivo - solo guardar como varchar
        'fecha_hora_pago', // Obligatorio si es transferencia o deposito
        'numero_operacion', // Obligatorio si es transferencia o deposito
        'saldo_inicial', // monto inicial
        'saldo_actual', // monto actual del anticipio, al inicio es igual que el monto inicial pero luego se va descontando
        'evidencias', // json
        'esta_anulado', // bool
        'fecha_hora_anulacion', // datetime
        'created_at', // fecha de registro en el sistema
        'estado', // Con Saldo / Sin Saldo / Anulado
    ];
}
