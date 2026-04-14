<?php

namespace App\Shared\Enums\SolicitudReabastecimiento;

enum EstadoSolicitudEntregaDetalle: string
{
    case EnDespacho = "En Despacho";
    case RecepcionadoParcialmente = "Recepcionado Parcialmente";
    case RecepcionCompleta = "Recepción Completa";
}
