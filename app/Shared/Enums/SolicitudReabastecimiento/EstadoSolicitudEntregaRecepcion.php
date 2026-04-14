<?php

namespace App\Shared\Enums\SolicitudReabastecimiento;

enum EstadoSolicitudEntregaRecepcion: string
{
    case RecepcionadoParcialmente = "Recepcionado Parcialmente";
    case RecepcionCompleta = "Recepción Completa";
}
