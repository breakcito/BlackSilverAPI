<?php

namespace App\Shared\Enums\Carbon;

/**
 * Tipos de gasto de producción asociados a un zarandeo de carbón.
 * 'Otro' obliga a describir el concepto en `gasto_produccion_carbon.otro_gasto`.
 */
enum TipoGastoZarandeo: string
{
    case Pala = 'Pala';
    case Lanza = 'Lanza';
    case Agua = 'Agua';
    case Escogedores = 'Escogedores';
    case Otro = 'Otro';

    /**
     * Pala y Lanza se tarifan por horas trabajadas (hora_inicio / hora_fin).
     */
    public function esPorHoras(): bool
    {
        return $this === self::Pala || $this === self::Lanza;
    }
}
