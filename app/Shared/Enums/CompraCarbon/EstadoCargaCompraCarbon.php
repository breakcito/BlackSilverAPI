<?php

namespace App\Shared\Enums\CompraCarbon;

enum EstadoCargaCompraCarbon: string
{
    case EnLiquidacion = 'En Liquidación';
    case Pagado = 'Pagado';
    case Anulado = 'Anulado';
}
