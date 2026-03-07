<?php

namespace App\Shared\Enums\SolicitudReabastecimiento;

enum SolicitudDetalleEstadoEnum: string
{
    case EsperandoAprobacion = "Esperando aprobación";
    case Rechazado = "Rechazado";
    case Aprobado = "Aprobado";
    case EnDespacho = "En despacho"; // La primera vez que se realiza una entrega
    case NuevaEntrega = "Nueva entrega"; // solo para la trazabilidad
    case Completado = "Completado"; // estado automatico
    case Cerrado = "Cerrado"; // estado manual - se decidio dejar de realizar entregas
}
