<?php

namespace App\Shared\Enums\VentaMineral;

/**
 * Quien asume el costo de un analisis de laboratorio.
 *
 * - Propio: lo realizo la empresa (Cupper & Hannia). El analisis se factura
 *   dentro del comprobante de analisis del despacho.
 * - Cliente: lo asume el cliente. El analisis NO se factura: solo se registra
 *   como referencia tecnica.
 */
enum AsumidoPorAnalisis: string
{
    case Propio = 'Propio';
    case Cliente = 'Cliente';

    /**
     * Solo los analisis propios entran al comprobante de analisis.
     */
    public function es_facturable(): bool
    {
        return $this === self::Propio;
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
