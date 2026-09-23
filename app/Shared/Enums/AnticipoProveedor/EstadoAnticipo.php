<?php

namespace App\Shared\Enums\AnticipoProveedor;

/**
 * Estado del anticipo de un proveedor (modulo carbon).
 *
 * - ConSaldo: el anticipo tiene dinero aun disponible (saldo_actual > 0).
 * - SinSaldo: el anticipo ya fue consumido por completo (saldo_actual = 0).
 * - Anulado: el anticipo fue cancelado antes de consumirse (esta_anulado = 1).
 *
 * La columna `estado` es varchar(64) NULLable en BD; cuando llega null
 * (registros viejos) se interpreta como ConSaldo por defecto.
 */
enum EstadoAnticipo: string
{
    case ConSaldo = 'Con Saldo';
    case SinSaldo = 'Sin Saldo';
    case Anulado = 'Anulado';
}
