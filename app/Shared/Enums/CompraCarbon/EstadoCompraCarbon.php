<?php

namespace App\Shared\Enums\CompraCarbon;

enum EstadoCompraCarbon: string
{
    case Preliminar = 'Preliminar';
    case Confirmado = 'Confirmado';
    case LiquidacionAprobada = 'Liquidación Aprobada';
    case EnProcesoPago = 'En Proceso de Pago';
    case Pagado = 'Pagado';
    case Anulado = 'Anulado';
}