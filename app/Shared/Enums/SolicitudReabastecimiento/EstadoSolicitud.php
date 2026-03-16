<?php

namespace App\Shared\Enums\SolicitudReabastecimiento;

enum EstadoSolicitud: string
{
    case Generada = 'Generada';
    case Cerrada = 'Cerrada';
    case EnProceso = 'En Proceso';
    case Anulada = 'Anulada';
}
