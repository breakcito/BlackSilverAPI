<?php

namespace App\Shared\Enums;

enum EstadoDetalleRequerimiento: string
{
    case Pendiente = 'Pendiente';
    case AprobacionLogistica = 'Aprobación - Logística';
    case DespachoIniciado = 'Despacho iniciado';
    case NuevaEntrega = 'Nueva entrega';
    case RechazadoLogistica = 'Rechazado - Logística';
    case Completado = 'Completado';
    case Cerrado = 'Cerrado';

    /**
     * Obtiene la glosa estándar para la trazabilidad
     */
    public function getGlosa(?string $dinamico = null): string
    {
        return match ($this) {
            self::Pendiente => 'Esperando aprobación',
            self::AprobacionLogistica => 'El jefe de logística aprobó el despacho para este producto',
            self::DespachoIniciado => 'El área de logística está procesando tu pedido',
            self::NuevaEntrega => "Se realizó la entrega de {$dinamico} producto(s)",
            self::RechazadoLogistica => 'Lo sentimos, tu producto fue rechazado por el área de logística',
            self::Completado => 'Tu producto ha sido completamente despachado',
            self::Cerrado => 'El despacho de tu producto ha terminado.',
        };
    }
}
