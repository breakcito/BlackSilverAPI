<?php

namespace App\Shared\Enums\CompraCarbon;

/**
 * Medio de pago de los pagos de compra de carbon (proveedor y transportista).
 *
 * Enum dedicado al proceso de pagos de carbon: no se recicla el
 * `AnticipoProveedor\MedioPago` porque cada proceso fisico maneja sus propias
 * reglas (README API, capa "Enums").
 *
 * - Transferencia: exige numero de operacion.
 * - Deposito: exige numero de operacion.
 * - Efectivo: el numero de operacion es opcional.
 *
 * La columna `medio_pago` es varchar(64) y se guarda el `->value`.
 */
enum MedioPago: string
{
    case Transferencia = 'Transferencia';
    case Deposito = 'Depósito';
    case Efectivo = 'Efectivo';

    /**
     * Los medios que se ejecutan contra una entidad financiera exigen
     * numero de operacion para poder conciliar con el estado de cuenta.
     */
    public function exige_numero_operacion(): bool
    {
        return $this !== self::Efectivo;
    }
}
