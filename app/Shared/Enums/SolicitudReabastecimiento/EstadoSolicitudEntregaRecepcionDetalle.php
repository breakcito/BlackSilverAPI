<?php

namespace App\Shared\Enums\SolicitudReabastecimiento;

enum EstadoSolicitudEntregaRecepcionDetalle: string
{
    case RecepcionadoParcialmente = "Recepcionado Parcialmente";
    case RecepcionCompleta = "Recepción Completa";
}
