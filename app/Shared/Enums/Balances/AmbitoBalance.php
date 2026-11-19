<?php

namespace App\Shared\Enums\Balances;

/**
 * Ambito del balance: separa la operacion de MINERAL de la de CARBON.
 * Cada ambito se resume por separado porque sus ingresos y egresos
 * provienen de cadenas de negocio distintas.
 */
enum AmbitoBalance: string
{
    case Mineral = 'MINERAL';
    case Carbon = 'CARBON';

    /**
     * Valores permitidos, para usarlos en reglas de validacion.
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_map(fn ($c) => $c->value, self::cases());
    }
}
