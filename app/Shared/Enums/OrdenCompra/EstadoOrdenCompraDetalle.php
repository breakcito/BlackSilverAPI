<?php

namespace App\Shared\Enums\OrdenCompra;

enum EstadoOrdenCompraDetalle: string
{
    case Pendiente    = 'Pendiente';
    case EnRecepcion  = 'En Recepción';
}
