<?php

namespace App\Shared\Enums\SolicitudReabastecimiento;

enum EstadoSolicitudDetalle: string
{
    case EsperandoAprobacion = "Esperando Aprobación";
    case Rechazado = "Rechazado";
    case Aprobado = "Aprobado";
    case EnDespacho = "En Despacho";
    case Cerrado = "Cerrado";
    case Completado = "Completado";
    case SolicitandoPrestamo = "Solicitando Préstamo";

    public function getGlosa(?string $dinamico = null): string
    {
        return match ($this) {
            self::EsperandoAprobacion => 'Esperando Aprobación de logística',
            self::Aprobado => 'Logística aprobó la solicitud de este producto',
            self::Rechazado => 'Lo sentimos, el producto no pudo ser atendido por logística',
            self::EnDespacho => 'Logística está procesando el despacho de este producto',
            self::Cerrado => 'El seguimiento de este ítem ha sido finalizado.',
            self::Completado => 'La atención de este ítem ha sido finalizada al 100%',
            self::SolicitandoPrestamo => 'Se ha solicitado un préstamo para atender este ítem',
        };
    }
}
