<?php

namespace App\Shared\Enums\SolicitudReabastecimiento;

enum EstadoEntrega: string
{
    case Procesada = 'Procesada';
    case Anulada = 'Anulada';
}
