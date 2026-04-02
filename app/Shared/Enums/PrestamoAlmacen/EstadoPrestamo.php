<?php

namespace App\Shared\Enums\PrestamoAlmacen;

enum EstadoPrestamo: string
{
    case Generado = 'Generado';
    case EnProceso = 'En Proceso'; // a penas se realiza una entrega
    case Completado = 'Completado';
    case Cerrado = 'Cerrado';
    case Anulado = 'Anulado';
}
