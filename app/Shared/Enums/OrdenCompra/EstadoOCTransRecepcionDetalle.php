<?php

namespace App\Shared\Enums\OrdenCompra;

enum EstadoOCTransRecepcionDetalle: string
{
    case RecepcionadoParcialmente = "Recepcionado Parcialmente";
    case RecepcionCompleta = "Recepción Completa";
}
