<?php

namespace App\Shared\Enums\PrestamoAlmacen;

enum EstadoPrestamo: string
{
    case Generado = "Generado";
    case EnDespacho = "En Despacho";
    case Anulado = "Anulado";
    case Cerrado = "Cerrado";
    case Completado = "Completado";
}
