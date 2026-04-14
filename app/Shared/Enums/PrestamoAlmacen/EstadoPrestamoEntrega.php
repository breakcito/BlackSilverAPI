<?php

namespace App\Shared\Enums\PrestamoAlmacen;

enum EstadoPrestamoEntrega: string
{
    case EnDespacho = "En Despacho";
    case RecepcionadoParcialmente = "Recepcionado Parcialmente";
    case RecepcionCompleta = "Recepcion Completa";
    case Anulado = "Anulado";
}
