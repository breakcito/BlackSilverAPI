<?php

namespace App\Shared\Enums\RequerimientoAlmacen;

enum EstadoDetalleEntrega: string
{
    case Entregado = 'Entregado';
    case Anulado = 'Anulado';
}
