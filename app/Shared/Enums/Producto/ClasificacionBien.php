<?php

namespace App\Shared\Enums\Producto;

enum ClasificacionBien: string
{
    case Suministro = 'Suministro';
    case Materiales = 'Materiales';
    case ActivoFijo = 'Activo Fijo';
}
