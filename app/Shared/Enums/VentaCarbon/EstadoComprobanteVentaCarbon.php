<?php

namespace App\Shared\Enums\VentaCarbon;

/**
 * Estados de un comprobante de venta de carbon.
 *
 * Enum dedicado al proceso de venta: no se recicla el de compras porque cada
 * proceso fisico maneja sus propias reglas (README API, capa "Enums").
 *
 * - Pendiente de Pago: se registro el comprobante y todavia no hay pagos.
 * - En Proceso de Pago: hay pagos, pero el saldo no esta cubierto.
 * - Pagado: el neto y la detraccion estan cubiertos.
 *
 * Es DERIVADO: lo recalcula `VentaCarbonPagosData` en cada pago.
 */
enum EstadoComprobanteVentaCarbon: string
{
    case PendienteDePago = 'Pendiente de Pago';
    case EnProcesoPago = 'En Proceso de Pago';
    case Pagado = 'Pagado';

    /**
     * Deriva el estado del comprobante a partir de los saldos.
     *
     * El `round` es indispensable: por IEEE-754 `100 - 99.99` da
     * `0.010000000000005116` y el comprobante nunca cerraria.
     */
    public static function resolver(
        float $totalNeto,
        float $montoDetraccion,
        float $netoPagado,
        float $detraccionPagada
    ): self {
        $tolerancia = 0.01;
        $netoCubierto = round($totalNeto - $netoPagado, 2) <= $tolerancia;
        $detraccionCubierta = round($montoDetraccion - $detraccionPagada, 2) <= $tolerancia;

        if ($netoCubierto && $detraccionCubierta) {
            return self::Pagado;
        }

        if ($netoPagado > 0 || $detraccionPagada > 0) {
            return self::EnProcesoPago;
        }

        return self::PendienteDePago;
    }
}
