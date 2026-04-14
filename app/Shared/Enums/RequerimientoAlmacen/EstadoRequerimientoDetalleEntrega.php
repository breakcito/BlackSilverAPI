<?php

namespace App\Shared\Enums\RequerimientoAlmacen;

enum EstadoRequerimientoDetalleEntrega: string
{
    case EnDespacho = "En Despacho";
    case RecepcionadoParcialmente = "Recepcionado Parcialmente";
    case RecepcionCompleta = "Recepción Completa";
}
