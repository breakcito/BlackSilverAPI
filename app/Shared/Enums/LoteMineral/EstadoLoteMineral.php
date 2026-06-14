<?php

namespace App\Shared\Enums\LoteMineral;

enum EstadoLoteMineral: string
{
    case Pendiente    = 'Pendiente';
    case EnProduccion = 'En Producción';
    case Finalizado   = 'Finalizado';
}
