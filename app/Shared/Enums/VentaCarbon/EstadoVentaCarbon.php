<?php

namespace App\Shared\Enums\VentaCarbon;

/**
 * Estado de una venta de carbon.
 *
 * Es DERIVADO: `venta_carbon` no tiene columna `estado`. Lo calcula
 * `VentaCarbonData::resolver_estado_venta()` a partir del estado de las
 * cargas de la venta.
 *
 * - Sin Cargas: la venta se registro sin despachar ninguna carga.
 * - En Curso: tiene cargas y al menos una todavia no confirmo su llegada.
 * - Completada: todas sus cargas confirmaron su llegada.
 */
enum EstadoVentaCarbon: string
{
    case SinCargas = 'Sin Cargas';
    case EnCurso = 'En Curso';
    case Completada = 'Completada';
}
