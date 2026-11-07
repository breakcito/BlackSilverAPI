<?php

namespace App\Shared\Enums\VentaMineral;

/**
 * Estados de una liquidacion de mineral.
 *
 * - Borrador: se registro la liquidacion y todavia no tiene comprobante.
 * - Aprobada: tiene comprobante registrado (la liquidacion respalda la
 *   factura).
 * - Pagada: su comprobante quedo pagado.
 *
 * Es DERIVADO del comprobante asociado: lo recalcula la capa Data.
 */
enum EstadoLiquidacionMineral: string
{
    case Borrador = 'Borrador';
    case Aprobada = 'Aprobada';
    case Pagada = 'Pagada';

    /**
     * Traduce el estado del comprobante asociado al estado de la liquidacion.
     */
    public static function desdeComprobante(?string $estadoComprobante): self
    {
        return match ($estadoComprobante) {
            EstadoComprobanteDespacho::Pagado->value => self::Pagada,
            EstadoComprobanteDespacho::EnProcesoPago->value,
            EstadoComprobanteDespacho::PendienteDePago->value => self::Aprobada,
            default => self::Borrador,
        };
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
