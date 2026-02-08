<?php

namespace App\Enums;

/**
 * Enum base para estados base de registros en el sistema.
 */
enum EstadoBase: string
{
    case Activo = 'Activo';
    case Inactivo = 'Inactivo';
    case Eliminado = 'Eliminado';
}
