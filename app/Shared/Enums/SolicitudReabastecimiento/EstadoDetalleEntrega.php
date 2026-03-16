<?php

namespace App\Shared\Enums\SolicitudReabastecimiento;

enum EstadoDetalleEntrega: string
{
    case Entregado = 'Entregado';
    case Anulado = 'Anulado';
}
