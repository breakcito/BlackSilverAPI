<?php

namespace App\Shared\Enums\RequerimientoAlmacen;

enum EstadoDetalleRequerimiento: string
{
    case EsperandoAprobacion = "Esperando aprobación";
    case Rechazado = "Rechazado";
    case Aprobado = "Aprobado";
    // cuando el almacenero decide primero consultar con logistica
    // y que esta le de el visto bueno
    case ConsultaLogistica = "Consultando a Logística";
    case RechazadoLogistica = "Rechazado por Logística";
    case AprobadoLogistica = "Aprobado por Logística";
    //
    case Completado = "Completado"; // estado automatico
    case Cerrado = "Cerrado"; // estado manual - se decidio dejar de realizar entregas
    //
    case EnDespacho = "En Despacho"; // La primera vez que se realiza una entrega
    case NuevaEntrega = "Nueva Entrega"; // solo para la trazabilidad

    /**
     * Obtiene la glosa estándar para la trazabilidad
     */
    public function getGlosa(?string $dinamico = null): string
    {
        return match ($this) {
            self::EsperandoAprobacion => 'Esperando aprobación',
            self::Rechazado => 'Lo sentimos, tu producto fue rechazado por el almacén',
            self::Aprobado => 'El encargado del almacén aprobó el despacho para este producto',
            self::ConsultaLogistica => 'La decisión ha pasado a manos del área de Logística',
            self::RechazadoLogistica => 'Lo sentimos, tu producto fue rechazado por el área de Logística',
            self::AprobadoLogistica => 'El área de Logística aprobó el despacho para este producto',
            self::EnDespacho => 'El almacén está procesando tu pedido',
            self::NuevaEntrega => "Se realizó la entrega de {$dinamico} producto(s)",
            self::Completado => 'Tu producto ha sido completamente despachado',
            self::Cerrado => 'El despacho de tu producto ha terminado.',
        };
    }
}
