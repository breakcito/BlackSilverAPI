<?php

namespace App\Shared\Enums\PrestamoAlmacen;

enum EstadoPrestamoEntregaDetalle: string
{
    case EnDespacho = "En Despacho";
    case RecepcionadoParcialmente = "Recepcionado Parcialmente";
    case RecepcionCompleta = "Recepción Completa";
}
