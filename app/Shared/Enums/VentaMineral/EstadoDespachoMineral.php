<?php

namespace App\Shared\Enums\VentaMineral;

/**
 * Estados de un despacho de mineral a molienda.
 *
 * Enum dedicado al proceso de venta de mineral: no se recicla ningun otro
 * enum porque cada proceso fisico maneja sus propias reglas (README API,
 * capa "Enums").
 *
 * - Registrado: el despacho existe pero todavia no tiene fletes.
 * - En Proceso: tiene fletes registrados y la carga esta en transito.
 * - Liquidado: el mineral ya se liquido con el cliente.
 */
enum EstadoDespachoMineral: string
{
    case Registrado = 'Registrado';
    case EnProceso = 'En Proceso';
    case Liquidado = 'Liquidado';

    /**
     * Valores permitidos, para usarlos en reglas de validacion.
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_map(fn ($c) => $c->value, self::cases());
    }
}
