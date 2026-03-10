<?php

namespace App\Shared\Enums;

/**
 * Enum base para estados base de registros en el sistema.
 */
enum EstadoBase: string
{
    case Activo = 'Activo';
    case Inactivo = 'Inactivo';
    case Eliminado = 'Eliminado';
    //
    case Activa = 'Activa';
    case Inactiva = 'Inactiva';
    case Eliminada = 'Eliminada';
}
