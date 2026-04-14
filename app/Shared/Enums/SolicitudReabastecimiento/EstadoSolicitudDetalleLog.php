<?php

namespace App\Shared\Enums\SolicitudReabastecimiento;

enum EstadoSolicitudDetalleLog: string
{
    case EsperandoAprobacion = "Esperando Aprobación";
    case Rechazado = "Rechazado";
    case Aprobado = "Aprobado";
    case EnDespacho = "En Despacho";
    case Cerrado = "Cerrado";
    case Completado = "Completado";
    case NuevaEntrega = "Nueva Entrega";
    case SolicitandoPrestamo = "Solicitando Préstamo";

    public function getGlosa(?string $dinamico = null): string
    {
        return match ($this) {
            self::EsperandoAprobacion => "Esperando Aprobación",
            self::Rechazado => "Rechazado",
            self::Aprobado => "Aprobado",
            self::EnDespacho => "En Despacho",
            self::Cerrado => "Cerrado",
            self::Completado => "Completado",
            self::NuevaEntrega => $dinamico ? "Se han entregado $dinamico productos" : "Nueva Entrega",
            self::SolicitandoPrestamo => "Solicitando Préstamo",
        };
    }
}
