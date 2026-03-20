<?php

namespace App\Shared\Enums\SolicitudReabastecimiento;

enum EstadoEntrega: string
{
    case Procesada = 'Procesada';
    case Recibida = 'Recibida';
    case Anulada = 'Anulada';
}
