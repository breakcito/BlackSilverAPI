<?php

namespace App\Shared\Enums\Cotizacion;

enum EstadoCotizacion: string
{
    case GENERADA   = 'Generada';
    case APROBADA   = 'Aprobada';
    case DESESTIMADA = 'Desestimada';
}
