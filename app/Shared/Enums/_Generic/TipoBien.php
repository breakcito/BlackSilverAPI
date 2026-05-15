<?php

namespace App\Shared\Enums\_Generic;

enum TipoBien: string
{
    case Suministro = 'Suministro';
    case Repuesto = 'Repuesto';
    case Herramienta = 'Herramienta';
    case EPP = 'EPP';
    case Material = 'Material';
    case ActivoFijo = 'Activo Fijo';
}
