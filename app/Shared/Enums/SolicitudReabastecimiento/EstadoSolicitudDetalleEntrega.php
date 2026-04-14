<?php
 
namespace App\Shared\Enums\SolicitudReabastecimiento;
 
enum EstadoSolicitudDetalleEntrega: string
{
    case EnDespacho = "En Despacho";
    case RecepcionadoParcialmente = "Recepcionado Parcialmente";
    case RecepcionCompleta = "Recepcion Completa";
}
