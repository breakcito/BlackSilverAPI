<?php

namespace App\Shared\Enums\RequerimientoAlmacen;

enum EstadoRequerimiento: string
{
    case Generada = 'Generado';
    case Cerrada = 'Cerrado';
    case Anulada = 'Anulado';
}
