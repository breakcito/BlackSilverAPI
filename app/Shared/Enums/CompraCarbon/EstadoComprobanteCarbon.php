<?php

namespace App\Shared\Enums\CompraCarbon;

/**
 * Estado de un comprobante de compra de carbon (proveedor o transportista).
 *
 * Enum dedicado al proceso de pagos de carbon. El estado es derivado: lo
 * recalcula `CompraCarbonPagosService` cada vez que se registra un pago,
 * comparando el avance pagado contra `total_neto` y `monto_detraccion`.
 *
 * - PendienteDePago: no tiene pagos registrados.
 * - EnProcesoPago: tiene pagos, pero queda saldo en el total neto, en la
 *   detraccion, o en ambos.
 * - Pagado: el total neto y el monto de detraccion estan cubiertos en su
 *   totalidad.
 */
enum EstadoComprobanteCarbon: string
{
    case PendienteDePago = 'Pendiente de Pago';
    case EnProcesoPago = 'En Proceso de Pago';
    case Pagado = 'Pagado';

    /**
     * Traduce el avance real del comprobante al estado que corresponde.
     *
     * @param float $totalNeto      Monto que se paga al proveedor/transportista.
     * @param float $montoDetraccion Monto retenido que se deposita en la cuenta
     *                               de detraccion designada por SUNAT.
     * @param float $netoPagado     Suma de pagos con es_para_detraccion = 0.
     * @param float $detraccionPagada Suma de pagos con es_para_detraccion = 1.
     */
    public static function resolver(
        float $totalNeto,
        float $montoDetraccion,
        float $netoPagado,
        float $detraccionPagada
    ): self {
        // Tolerancia de 1 centimo. Cubre dos cosas: el redondeo a DECIMAL(2)
        // de MySQL y los residuales de un céntimo, que no justifican dejar un
        // comprobante eternamente "En proceso de pago" por una moneda.
        $tolerancia = 0.01;

        // El `round` es indispensable: en IEEE-754 la resta 100 - 99.99 vale
        // 0.010000000000005116, y sin redondear un comprobante exactamente
        // saldado quedaria para siempre en "En proceso de pago".
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
