<?php

namespace App\Shared\Enums\OrdenCompra;

enum EstadoOrdenCompraDetalleLog: string
{
    case Pendiente      = 'Pendiente';
    case EnRecepcion    = 'En Recepción';
    case NuevaRecepcion = 'Nueva Recepción';

    public function getGlosa(?string $dinamico = null): string
    {
        return match ($this) {
            self::Pendiente      => 'El ítem está pendiente de recepción.',
            self::EnRecepcion    => 'El ítem se encuentra en proceso de recepción.',
            self::NuevaRecepcion => "Se registró una nueva recepción de {$dinamico} unidad(es).",
        };
    }
}
