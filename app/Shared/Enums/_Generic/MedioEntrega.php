<?php

namespace App\Shared\Enums\_Generic;

enum MedioEntrega: string
{
    case Terceros = 'Terceros'; // Proveedores de transporte
    case Agencia = 'Agencia'; // Agencias de transporte
    case Propio = 'Propio'; // Medio propio
}
