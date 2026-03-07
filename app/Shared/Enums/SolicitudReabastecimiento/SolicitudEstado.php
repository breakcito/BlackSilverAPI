<?php

namespace App\Shared\Enums\SolicitudReabastecimiento;

enum SolicitudEstado: string
{
    case Generada = 'Generada';
    case Cerrada = 'Cerrada';
    case Anulada = 'Anulada';
}
