<?php

namespace App\Shared\Enums;

enum OrigenMovimiento: string
{
    case NuevoLote = 'Nuevo lote';
    case Entrega = 'Entrega';
    case Recepcion = 'Recepcion';
    case Devolucion = 'Devolucion';
    case Inventario = 'Inventario';
}
