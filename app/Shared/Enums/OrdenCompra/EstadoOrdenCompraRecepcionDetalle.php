<?php

namespace App\Shared\Enums\OrdenCompra;

enum EstadoOrdenCompraRecepcionDetalle: string
{
    case RecepcionadoParcialmente = "Recepcionado Parcialmente";
    case RecepcionCompleta = "Recepción Completa";
}
