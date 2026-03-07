<?php

namespace App\Shared\Enums\Kardex;

enum OrigenMovimiento: string
{
    case NuevoLote = 'Nuevo lote';
    case Entrega = 'Entrega';
    case Recepcion = 'Recepcion';
    case Devolucion = 'Devolucion';
    case Inventario = 'Inventario';
    case AjusteStock = 'Ajuste de Stock';
}
