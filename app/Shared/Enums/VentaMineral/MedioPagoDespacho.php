<?php

namespace App\Shared\Enums\VentaMineral;

/**
 * Medio de pago de los pagos de los comprobantes de despacho.
 *
 * - Transferencia: se paga desde una cuenta.
 * - Deposito: se deposita en la cuenta de la empresa.
 * - Efectivo: pago en efectivo, no hay conciliacion bancaria.
 */
enum MedioPagoDespacho: string
{
    case Transferencia = 'Transferencia';
    case Deposito = 'Depósito';
    case Efectivo = 'Efectivo';

    /**
     * Transferencia y Deposito se concilian contra una entidad financiera,
     * asi que exigen numero de operacion. Efectivo no.
     */
    public function exige_numero_operacion(): bool
    {
        return $this !== self::Efectivo;
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
