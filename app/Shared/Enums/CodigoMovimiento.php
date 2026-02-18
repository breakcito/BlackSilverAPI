<?php

namespace App\Shared\Enums;

enum CodigoMovimiento: string
{
    case NuevoLote = 'Nuevo lote';
    case Entrega = 'Entrega';
    case Recepcion = 'Recepcion';
    case Devolucion = 'Devolucion';
    case Inventario = 'Inventario';
}
