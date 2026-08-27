<?php

namespace App\Shared\Enums\_Generic;

enum EstadoComprobante: string
{
    case Pendiente = 'Pendiente';
    case EnProceso = 'En Proceso';
    case Pagado = 'Pagado';
}