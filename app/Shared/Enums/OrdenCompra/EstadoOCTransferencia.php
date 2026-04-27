<?php

namespace App\Shared\Enums\OrdenCompra;

enum EstadoOCTransferencia: string
{
    case EnDespacho = "En Despacho";
    case RecepcionadoParcialmente = "Recepcionado Parcialmente";
    case RecepcionCompleta = "Recepción Completa";
}
