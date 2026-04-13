<?php

namespace App\Shared\Enums\Cotizacion;

enum MetodoPago: string
{
    case CONTADO = 'Contado';
    case CREDITO = 'Crédito';
}
