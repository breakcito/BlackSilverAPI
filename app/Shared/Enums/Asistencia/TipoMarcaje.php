<?php

namespace App\Shared\Enums\Asistencia;

/**
 * Tipos de marcaje soportados en el sistema de Asistencia.
 *
 * - Ingreso: marca el inicio de la jornada (o el primer turno del día).
 * - Salida: marca el fin de la jornada (o el fin del último turno del día).
 *
 * Un valor NULL en la columna `tipo_marcaje` significa que el proceso no
 * llegó a completarse (por timeout, cancelación o error). En ese caso el
 * registro del marcaje sirve como log/evidencia pero no genera asistencia.
 */
enum TipoMarcaje: string
{
    case Ingreso = 'Ingreso';
    case Salida = 'Salida';
}
