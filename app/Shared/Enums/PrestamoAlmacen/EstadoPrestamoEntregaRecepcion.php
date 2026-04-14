<?php

namespace App\Shared\Enums\PrestamoAlmacen;

enum EstadoPrestamoEntregaRecepcion: string
{
    case RecepcionadoParcialmente = "Recepcionado Parcialmente";
    case RecepcionCompleta = "Recepción Completa";
}
