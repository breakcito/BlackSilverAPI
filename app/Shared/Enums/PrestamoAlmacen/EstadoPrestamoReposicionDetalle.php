<?php

namespace App\Shared\Enums\PrestamoAlmacen;

enum EstadoPrestamoReposicionDetalle: string
{
    case EnDespacho = "En Despacho";
    case RecepcionadoParcialmente = "Recepcionado Parcialmente";
    case RecepcionCompleta = "Recepcion Completa";
}
