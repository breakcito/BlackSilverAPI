<?php

namespace App\Shared\Enums\OrdenCompra;

enum EstadoOCTransRecepcion: string
{
    case RecepcionadoParcialmente = "Recepcionado Parcialmente";
    case RecepcionCompleta = "Recepción Completa";
}
