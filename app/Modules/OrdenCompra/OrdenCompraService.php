<?php

namespace App\Modules\OrdenCompra;

use App\Shared\Responses\ApiResponse;
use App\Modules\OrdenCompra\Data\OrdenCompraData;

class OrdenCompraService
{
    /**
     * Listar todas las órdenes de compra con sus cabeceras
     */
    public static function listar(): array
    {
        $ordenes  = OrdenCompraData::get_listado();
        return ApiResponse::success(['ordenes' => $ordenes]);
    }

    /**
     * Obtener el detalle de una orden de compra específica
     *
     * @param int $id_orden_compra
     */
    public static function get_detalles(int $id_orden_compra): array
    {
        $detalles = OrdenCompraData::get_detalles($id_orden_compra);
        return ApiResponse::success(['detalles' => $detalles]);
    }
}
