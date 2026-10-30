<?php

namespace App\Shared\Enums\VentaCarbon;

/**
 * Estados de una carga de venta de carbon.
 *
 * - En Camino: la carga fue despachada (salio del almacen de la empresa) y
 *   todavia no se confirmo su llegada al almacen del cliente.
 * - Llegada Confirmada: el cliente recibio la carga y se registro el
 *   analisis, el peso confirmado y el vehiculo.
 * - En Liquidacion: la carga ya pertenece a una liquidacion.
 * - Pagado: su comprobante quedo pagado.
 */
enum EstadoCargaVentaCarbon: string
{
    case EnCamino = 'En Camino';
    case LlegadaConfirmada = 'Llegada Confirmada';
    case EnLiquidacion = 'En Liquidación';
    case Pagado = 'Pagado';
}
