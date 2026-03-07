<?php

namespace App\Shared\Enums;

enum RequerimientoEstado: string
{
    case Generada = 'Generado';
    case Cerrada = 'Cerrado';
    case Anulada = 'Anulado';
}
