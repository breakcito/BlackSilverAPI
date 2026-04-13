<?php

namespace App\Shared\Enums\PrestamoAlmacen;

enum EstadoPrestamo: string
{
    case Generado = 'Generado';
    case EnDespacho = 'En Despacho';
    case Completado = 'Completado';
    case Cerrado = 'Cerrado';
    case Anulado = 'Anulado';
}
