<?php

namespace App\Modules\SolicitudesReabastecimientoAtencion\Service;

use App\Data\ProductosData;
use App\Shared\Responses\ApiResponse;
use App\Modules\SolicitudesReabastecimientoAtencion\Data\AuxData;

class AuxService
{
    /**
     * Obtiene los almacenes que tienen stock de los productos solicitados
     */
    public static function get_almacenes_con_stock(int $id_almacen_excluido, array $ids_productos)
    {
        $data = AuxData::get_almacenes_con_stock($id_almacen_excluido, $ids_productos);
        return ApiResponse::success($data);
    }

    /**
     * Obtiene el stock total de uno o varios productos en un almacén específico.
     * Solo suma el stock de lotes activos y que no estén vencidos.
     */
    public static function get_stock_total_almacen_por_productos(int $id_almacen, array $ids_productos)
    {
        $data = ProductosData::get_stock_total_almacen_por_productos($id_almacen, $ids_productos);
        return ApiResponse::success($data);
    }
}
