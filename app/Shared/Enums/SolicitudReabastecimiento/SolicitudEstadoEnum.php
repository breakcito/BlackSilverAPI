<?php

namespace App\Shared\Enums\SolicitudReabastecimiento;

enum SolicitudEstadoEnum: string
{
    case Generada = 'Generada';
    case Cerrada = 'Cerrada';
    case Anulada = 'Anulada';
}
