<?php

namespace App\Shared\Enums\PrestamoAlmacen;

enum EstadoEntregaPrestamo: string
{
    case EnDespacho   = 'En despacho';
    case Confirmada   = 'Entrega confirmada';
    case Anulada      = 'Anulada';

    public function getGlosa(?string $dinamico = null): string
    {
        return match ($this) {
            self::EnDespacho => 'La entrega ha sido registrada y los productos están en camino',
            self::Confirmada => 'El almacén solicitante confirmó la recepción de los productos',
            self::Anulada    => 'La entrega fue anulada',
        };
    }
}
