<?php

namespace App\Modules\OrdenesCompra\Service;

use App\Shared\Responses\ApiResponse;
use App\Modules\OrdenesCompra\Data\OrdenCompraData;

class OrdenCompraService
{
    /**
     * Listar todas las órdenes de compra - solo cabeceras
     */
    public static function get_ordenes(?int $mes = null, ?int $yearcito = null)
    {
        $ordenes = OrdenCompraData::get_ordenes(mes: $mes, yearcito: $yearcito);
        return ApiResponse::success($ordenes);
    }

    /**
     * Obtener el detalle de una orden de compra específica
     *
     * @param int $ids_ordenes_compra
     */
    public static function get_detalles(int|array $ids_ordenes_compra): array
    {
        $detalles = OrdenCompraData::get_detalles($ids_ordenes_compra);
        return ApiResponse::success($detalles);
    }

    /**
     * Obtener el seguimiento de un detalle de OC
     */
    public static function get_seguimiento(int $id_detalle): array
    {
        $logs = OrdenCompraData::get_seguimiento($id_detalle);
        return ApiResponse::success($logs);
    }
}
