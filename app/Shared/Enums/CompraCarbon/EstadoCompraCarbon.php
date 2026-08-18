<?php

namespace App\Shared\Enums\CompraCarbon;

enum EstadoCompraCarbon: string
{
    case Pendiente = 'Pendiente';
    case Aprobado = 'Aprobado';
    case Anulado = 'Anulado';
}