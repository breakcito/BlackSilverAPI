<?php

namespace App\Shared\Enums\LaborMinera;

enum TipoLabor: string
{
    case Bypass = "Bypass";
    case Crucero = "Crucero";
    case Tajo = "Tajo";
    case Rampa = "Rampa";
    case Chimenea = "Chimenea";
}
