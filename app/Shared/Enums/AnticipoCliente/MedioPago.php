<?php

namespace App\Shared\Enums\AnticipoCliente;

/**
 * Medio de pago de un anticipo RECIBIDO de un cliente de carbon.
 *
 * A diferencia del anticipo a proveedor (donde la plata SALE de la
 * empresa), aqui la plata ENTRA a la empresa:
 *
 * - Transferencia: requiere id_cuenta_bancaria_empresa (cuenta donde se
 *   deposito), fecha_hora_pago y numero_operacion (obligatorios).
 * - Deposito: idem Transferencia.
 * - Efectivo: la cuenta bancaria de la empresa es opcional y la fecha /
 *   numero de operacion no son obligatorios (no hay operacion bancaria).
 *
 * Proceso fisico distinto al del anticipo a proveedor, por eso tiene su
 * propio enum: los enums nunca se reciclan entre procesos distintos
 * (README API, seccion "Enums").
 */
enum MedioPago: string
{
    case Transferencia = 'Transferencia';
    case Deposito = 'Depósito';
    case Efectivo = 'Efectivo';
}
