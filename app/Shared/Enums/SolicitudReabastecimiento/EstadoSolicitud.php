<?php

namespace App\Shared\Enums\SolicitudReabastecimiento;

enum EstadoSolicitud: string
{
    case Generada = "Generada";
    case EnDespacho = "En Despacho";
    case Anulada = "Anulada";
    case Cerrada = "Cerrada";
    case Completada = "Completada";
}
