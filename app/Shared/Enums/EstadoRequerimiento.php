<?php

namespace App\Shared\Enums;

enum EstadoRequerimiento: string
{
    case Generada = 'Generada';
    case Cerrada = 'Cerrada';
    case Anulada = 'Anulada';
}
