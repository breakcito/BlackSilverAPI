<?php

namespace App\Shared\Enums\Kardex;

enum OrigenMovimiento: string
{
    case NuevoLote = 'Nuevo lote';
    case Entrega = 'Entrega'; // salida - se entrega al personal que solicita
    case Recepcion = 'Recepcion'; // entrada - se recepciona mas stock
    case Devolucion = 'Devolucion'; // salida - se devuelve en diferentes procesos
    case AjusteStock = 'Ajuste de Stock'; // mixto - el almacenero edito manualmente el stock
}
