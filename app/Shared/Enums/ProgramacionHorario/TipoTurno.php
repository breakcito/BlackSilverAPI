<?php

namespace App\Shared\Enums\ProgramacionHorario;

/**
 * Tipos de turno laboral soportados en el sistema.
 *
 * - Dia: turno cuya entrada y salida ocurren en el mismo día natural.
 * - Noche: turno cuya salida ocurre al día siguiente natural (cruza medianoche).
 */
enum TipoTurno: string
{
    case Dia = 'Dia';
    case Noche = 'Noche';
}
