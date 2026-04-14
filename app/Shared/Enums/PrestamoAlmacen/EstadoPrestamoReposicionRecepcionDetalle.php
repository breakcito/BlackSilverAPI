<?php

namespace App\Shared\Enums\PrestamoAlmacen;

enum EstadoPrestamoReposicionRecepcionDetalle: string
{
    case RecepcionadoParcialmente = "Recepcionado Parcialmente";
    case RecepcionCompleta = "Recepcion Completa";
}
