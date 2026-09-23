<?php

namespace App\Shared\Enums\AnticipoProveedor;

/**
 * Medio de pago de un anticipo a proveedor (modulo carbon).
 *
 * - Transferencia: requiere id_cuenta_bancaria_empresa, fecha_hora_pago y
 *   numero_operacion (obligatorios).
 * - Deposito: idem Transferencia.
 * - Efectivo: los 3 campos anteriores son opcionales.
 *
 * La columna `medio_pago` es varchar(64) NULLable en BD; un NULL significa
 * "sin medio de pago registrado" (no se muestra en selects, se acepta
 * como "no especificado" hacia atras).
 */
enum MedioPago: string
{
    case Transferencia = 'Transferencia';
    case Deposito = 'Depósito';
    case Efectivo = 'Efectivo';
}
