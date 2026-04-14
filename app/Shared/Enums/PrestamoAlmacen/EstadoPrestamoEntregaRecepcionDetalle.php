<?php

namespace App\Shared\Enums\PrestamoAlmacen;

enum EstadoPrestamoEntregaRecepcionDetalle: string
{
    case RecepcionadoParcialmente = "Recepcionado Parcialmente";
    case RecepcionCompleta = "Recepcion Completa";
}
