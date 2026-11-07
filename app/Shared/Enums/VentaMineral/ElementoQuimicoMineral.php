<?php

namespace App\Shared\Enums\VentaMineral;

/**
 * Elemento quimico por el que se liquida el mineral.
 *
 * La liquidacion se registra POR ELEMENTO: un mismo despacho puede tener una
 * liquidacion de oro y otra de plata.
 */
enum ElementoQuimicoMineral: string
{
    case Oro = 'Oro';
    case Plata = 'Plata';

    /**
     * Valores permitidos, para usarlos en reglas de validacion.
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_map(fn ($c) => $c->value, self::cases());
    }
}
