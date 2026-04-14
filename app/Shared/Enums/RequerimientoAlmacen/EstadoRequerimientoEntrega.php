<?php
 
namespace App\Shared\Enums\RequerimientoAlmacen;
 
enum EstadoRequerimientoEntrega: string
{
    case EnDespacho = "En Despacho";
    case RecepcionadoParcialmente = "Recepcionado Parcialmente";
    case RecepcionCompleta = "Recepcion Completa";
}
