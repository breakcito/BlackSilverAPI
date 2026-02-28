<?php

namespace App\Shared\Enums;

enum EstadoDetallePrestamo: string
{
    case Pendiente = 'Pendiente';
    case Aprobado = 'Aprobado';
    case DespachoIniciado = 'Despacho iniciado';
    case NuevaEntrega = 'Nueva entrega';
    case Completado = 'Completado';
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
            self::Pendiente => 'Esperando respuesta del almacén',
            self::Aprobado => 'El almacén aprobó el préstamo de este producto',
            self::DespachoIniciado => 'Se esta procesando la entrega de su prestamo',
            self::NuevaEntrega => "Has recibido una entrega parcial de {$dinamico} producto(s)",
            self::Completado => 'Has recibido la totalidad de las unidades prestadas',
            self::DevolucionParcial => "Se ha registrado la devolución de {$dinamico} producto(s)",
            self::DevolucionTotal => 'Se ha devuelto la totalidad del producto prestado',
            self::Rechazado => 'El préstamo no pudo ser atendido por el almacén',
            self::Cerrado => 'El seguimiento de este ítem ha sido finalizado',
        };
    }
}
