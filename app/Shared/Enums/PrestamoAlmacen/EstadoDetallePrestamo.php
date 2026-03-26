<?php

namespace App\Shared\Enums\PrestamoAlmacen;

enum EstadoDetallePrestamo: string
{
    case Pendiente = 'Pendiente';
    case Aprobado = 'Aprobado';
    case DespachoIniciado = 'Despacho iniciado';
    case NuevaEntrega = 'Nueva entrega';
    case EntregaCompleta = 'Entrega completa';
    case DevolucionParcial = 'Devolución parcial';
    case DevolucionTotal = 'Devolución total';
    case Rechazado = 'Rechazado';
    case Cerrado = 'Cerrado';

    /**
     * Obtiene la glosa estándar para la trazabilidad
     */
    public function getGlosa(?string $dinamico = null): string
    {
        return match ($this) {
            self::Pendiente => 'Esperando aprobación del almacén',
            self::Aprobado => 'El encargado del almacén aprobó el préstamo de este producto',
            self::DespachoIniciado => 'El almacén está procesando el despacho de este producto',
            self::NuevaEntrega => "Se registró una entrega de {$dinamico} producto(s)",
            self::EntregaCompleta => 'La atención de este ítem ha sido finalizada al 100%',
            self::DevolucionParcial => "Se ha registrado la devolución de {$dinamico} producto(s)",
            self::DevolucionTotal => 'Se ha devuelto la totalidad del producto prestado',
            self::Rechazado => 'Lo sentimos, el préstamo no pudo ser atendido por el almacén',
            self::Cerrado => 'El seguimiento de este ítem ha sido finalizado.',
        };
    }
}
