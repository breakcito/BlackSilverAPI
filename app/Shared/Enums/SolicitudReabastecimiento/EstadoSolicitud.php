<?php

namespace App\Shared\Enums\SolicitudReabastecimiento;

enum EstadoSolicitud: string
{
    case Generada = 'Generada';
    case Cerrada = 'Cerrada';
    case Anulada = 'Anulada';
}
