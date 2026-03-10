<?php

namespace App\Shared\Enums\RequerimientoAlmacen;

enum EstadoEntrega: string
{
    case Procesada = 'Procesada';
    case Anulada = 'Anulada';
}
