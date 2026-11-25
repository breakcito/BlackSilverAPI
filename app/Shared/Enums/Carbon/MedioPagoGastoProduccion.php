<?php

namespace App\Shared\Enums\Carbon;

/**
 * Medio de pago de los gastos de produccion de carbon (pala, lanza, agua,
 * escogedores, etc.).
 *
 * Enum dedicado a este proceso: no se recicla el `CompraCarbon\MedioPago`
 * porque el pago de un gasto a un proveedor de servicio no exige cuenta
 * bancaria ni numero de operacion (README API, capa "Enums").
 *
 * La columna `medio_pago` es varchar(64) y se guarda el `->value`.
 */
enum MedioPagoGastoProduccion: string
{
    case Efectivo = 'Efectivo';
    case Transferencia = 'Transferencia';
    case Yape = 'Yape';
}
