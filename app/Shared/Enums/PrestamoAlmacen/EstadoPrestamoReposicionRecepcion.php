<?php

namespace App\Shared\Enums\PrestamoAlmacen;

enum EstadoPrestamoReposicionRecepcion: string
{
    case RecepcionadoParcialmente = "Recepcionado Parcialmente";
    case RecepcionCompleta = "Recepción Completa";
}
