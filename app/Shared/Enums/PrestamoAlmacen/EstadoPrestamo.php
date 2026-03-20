<?php

namespace App\Shared\Enums\PrestamoAlmacen;

enum EstadoPrestamo: string
{
    case Generado = 'Generado';
    case EnProceso = 'En Proceso';
    case Completado = 'Completado';
    case Finalizado = 'Finalizado';
    case Anulado = 'Anulado';
}
