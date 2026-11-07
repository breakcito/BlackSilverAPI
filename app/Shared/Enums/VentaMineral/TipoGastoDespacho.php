<?php

namespace App\Shared\Enums\VentaMineral;

/**
 * Tipo de gasto registrado al despachar mineral a molienda.
 *
 * - Winche: gasto de maquinaria (wincha).
 * - Winchadores: gasto de personal (wincheros).
 */
enum TipoGastoDespacho: string
{
    case Winche = 'Winche';
    case Winchadores = 'Winchadores';

    /**
     * Valores permitidos, para usarlos en reglas de validacion.
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_map(fn ($c) => $c->value, self::cases());
    }
}
