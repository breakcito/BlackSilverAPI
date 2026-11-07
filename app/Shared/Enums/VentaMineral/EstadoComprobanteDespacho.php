<?php

namespace App\Shared\Enums\VentaMineral;

/**
 * Estados de un comprobante de despacho (flete, molienda, analisis o
 * liquidacion).
 *
 * - Pendiente de Pago: se registro el comprobante y todavia no hay pagos.
 * - En Proceso de Pago: hay pagos, pero el saldo no esta cubierto.
 * - Pagado: el neto y la detraccion estan cubiertos.
 *
 * Es DERIVADO: lo recalcula la capa Data en cada pago.
 */
enum EstadoComprobanteDespacho: string
{
    case PendienteDePago = 'Pendiente de Pago';
    case EnProcesoPago = 'En proceso de Pago';
    case Pagado = 'Pagado';

    /**
     * Deriva el estado del comprobante a partir de los saldos.
     *
     * El `round` es indispensable: por IEEE-754 `100 - 99.99` da
     * `0.010000000000005116` y el comprobante nunca cerraria.
     */
    public static function resolver(
        float $netoAPagar,
        float $montoDetraccion,
        float $netoPagado,
        float $detraccionPagada
    ): self {
        $tolerancia = 0.01;
        $netoCubierto = round($netoAPagar - $netoPagado, 2) <= $tolerancia;
        $detraccionCubierta = round($montoDetraccion - $detraccionPagada, 2) <= $tolerancia;

        if ($netoCubierto && $detraccionCubierta) {
            return self::Pagado;
        }

        if ($netoPagado > 0 || $detraccionPagada > 0) {
            return self::EnProcesoPago;
        }

        return self::PendienteDePago;
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
