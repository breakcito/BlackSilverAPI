<?php

namespace App\Shared\Enums\OrdenCompra;

enum EstadoOrdenCompraRecepcion: string
{
    case RecepcionadoParcialmente = "Recepcionado Parcialmente";
    case RecepcionCompleta = "Recepción Completa";
}
