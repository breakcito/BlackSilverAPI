<?php

namespace App\Shared\Enums;

enum EstadoRequerimiento: string
{
    case Generada = 'Generada';
    case Pendiente = 'Pendiente';
    case Aprobado = 'Aprobado';
    case Atendido = 'Atendido';
    case Rechazado = 'Rechazado';
    case Anulado = 'Anulado';
}
