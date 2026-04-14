<?php

namespace App\Shared\Enums\PrestamoAlmacen;

enum EstadoPrestamoReposicion: string
{
    case EnDespacho = "En Despacho";
    case RecepcionadoParcialmente = "Recepcionado Parcialmente";
    case RecepcionCompleta = "Recepcion Completa";
}
