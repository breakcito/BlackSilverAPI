<?php

namespace App\Shared\Enums\Carbon;

/**
 * Tipo de operacion que agrupa un grupo de gastos de produccion.
 *
 * Un grupo es de UN solo tipo: los gastos de zarandear y los de mezclar son
 * operaciones distintas y se registran por separado.
 */
enum TipoOperacionGrupoGastos: string
{
    case Zarandeo = 'zarandeo';
    case Blending = 'blending';
}
