<?php

namespace App\Shared\Enums\VentaCarbon;

/**
 * Tipos de vehiculo que despachan carbon.
 *
 * Enum dedicado al proceso de venta de carbon: no se recicla ningun otro
 * enum porque cada proceso fisico maneja sus propias reglas (README API,
 * capa "Enums").
 *
 * - Tolva: el remolque lleva su propia placa, asi que la carga exige una
 *   segunda placa (`carga_venta_carbon.segunda_placa`).
 * - Burrita: una sola placa.
 *
 * El valor se graba en `vehiculo_carbon.tipo_vehiculo`.
 */
enum TipoVehiculoCarbon: string
{
    case Tolva = 'Tolva';
    case Burrita = 'Burrita';

    /**
     * Las tolvas necesitan la segunda placa de la carga.
     */
    public function exige_segunda_placa(): bool
    {
        return $this === self::Tolva;
    }

    /**
     * Valores permitidos, para usarlos en reglas de validacion.
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_map(fn ($c) => $c->value, self::cases());
    }
}
