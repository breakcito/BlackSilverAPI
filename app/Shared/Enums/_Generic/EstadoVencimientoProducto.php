<?php

namespace App\Shared\Enums\_Generic;

enum EstadoVencimientoProducto: string
{
    case NA = 'N/A';
    case SinFecha = 'Sin fecha';
    case Vigente = 'Vigente';
    case PorVencer = 'Por vencer';
    case Vencido = 'Vencido';
}
