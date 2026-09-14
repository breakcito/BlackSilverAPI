<?php

namespace App\Shared\Enums\_Generic;

/**
 * Tipos de turno laboral soportados en el sistema.
 *
 * Es un enum genérico (no del modulo ProgramacionHorario) porque
 * multiples modulos lo comparten: planilla, asistencia y
 * requerimiento de almacen.
 *
 * - Dia: turno cuya entrada y salida ocurren en el mismo día natural.
 * - Noche: turno cuya salida ocurre al día siguiente natural (cruza medianoche).
 */
enum TipoTurno: string
{
    case Dia = 'Dia';
    case Noche = 'Noche';
}
