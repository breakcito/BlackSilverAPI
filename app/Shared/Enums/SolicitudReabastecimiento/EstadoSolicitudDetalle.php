<?php

namespace App\Shared\Enums\SolicitudReabastecimiento;

enum EstadoSolicitudDetalle: string
{
    case EsperandoAprobacion = "Esperando aprobación";
    case Rechazado = "Rechazado";
    case Aprobado = "Aprobado";
    case EnDespacho = "En despacho"; // La primera vez que se realiza una entrega
    case NuevaEntrega = "Nueva entrega"; // solo para la trazabilidad
    case Completado = "Completado"; // estado automatico
    case Cerrado = "Cerrado"; // estado manual - se decidio dejar de realizar entregas

    /**
     * Obtiene la glosa estándar para la trazabilidad
     */
    public function getGlosa(?string $dinamico = null): string
    {
        return match ($this) {
            self::EsperandoAprobacion => 'Esperando aprobación',
            self::Rechazado => 'Lo sentimos, tu producto fue rechazado por el área de Logística',
            self::Aprobado => 'El área de Logística aprobó el despacho para este producto',
            self::EnDespacho => 'El área de Logística está procesando tu pedido',
            self::NuevaEntrega => "Se realizó la entrega de {$dinamico} producto(s)",
            self::Completado => 'Tu producto ha sido completamente despachado',
            self::Cerrado => 'El despacho de tu producto ha terminado.',
        };
    }
}
