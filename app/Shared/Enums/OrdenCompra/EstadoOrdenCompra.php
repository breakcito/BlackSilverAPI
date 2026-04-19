<?php

namespace App\Shared\Enums\OrdenCompra;

enum EstadoOrdenCompra: string
{
    case Generada      = 'Generada';
    case EnRecepcion   = 'En Recepción';
    case Anulada       = 'Anulada';
    case Cerrada       = 'Cerrada';
    case Completada    = 'Completada';
}
