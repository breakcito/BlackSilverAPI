<?php

namespace App\Modules\OrdenCompra\Service;

use App\Data\AlmacenesData;
use App\Data\LotesProductosData;
use App\Shared\Responses\ApiResponse;


class AuxService
{
    public static function get_almacenes()
    {
        $almacenes = AlmacenesData::get_almacenes();

        return ApiResponse::success($almacenes);
    }

    public static function get_lotes_disponibles(int $id_almacen_recepcionista, array $id_productos)
    {
        $lotes = LotesProductosData::get_lotes_disponibles($id_almacen_recepcionista, $id_productos);
        return ApiResponse::success($lotes);
    }
}
