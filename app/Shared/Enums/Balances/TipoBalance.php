<?php

namespace App\Shared\Enums\Balances;

/**
 * Signo del movimiento dentro del balance.
 *
 * - Ingreso: entra dinero a la empresa (venta de mineral, venta de carbon,
 *   alquiler de maquinaria a terceros).
 * - Egreso: sale dinero (consumo de inventario, gastos, compras, fletes).
 */
enum TipoBalance: string
{
    case Ingreso = 'INGRESO';
    case Egreso = 'EGRESO';

    /**
     * Valores permitidos, para usarlos en reglas de validacion.
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_map(fn ($c) => $c->value, self::cases());
    }
}
