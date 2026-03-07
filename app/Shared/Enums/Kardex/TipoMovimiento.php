<?php

namespace App\Shared\Enums\Kardex;

enum TipoMovimiento: string
{
    case Ingreso = 'Ingreso';
    case Salida = 'Salida';
}
