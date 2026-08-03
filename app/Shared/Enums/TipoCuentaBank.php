<?php

namespace App\Shared\Enums;

enum TipoCuentaBank: string
{
    case CuentaCorriente = 'Cuenta Corriente';
    case CuentaSueldo = 'Cuenta Sueldo';
}
