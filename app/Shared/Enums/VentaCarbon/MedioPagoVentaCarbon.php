<?php

namespace App\Shared\Enums\VentaCarbon;

/**
 * Medio de pago de los pagos de venta de carbon.
 *
 * - Transferencia: el cliente transfiere desde su cuenta.
 * - Deposito: el cliente deposita en la cuenta de la empresa.
 * - Efectivo: pago en efectivo, no hay conciliacion bancaria.
 *
 * El valor es el formato de cable: coincide con el que viaja en el payload.
 */
enum MedioPagoVentaCarbon: string
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
