<?php

namespace App\Views\PrestamosAlmacen\Service;

use App\Data\AlmacenesData;
use App\Data\LotesData as DataLotesData;
use App\Shared\Responses\ApiResponse;

class AuxService
{
    /**
     * Obtiene los almacenes
     */
    public static function get_almacenes(bool $es_principal = false)
    {
        $data = AlmacenesData::get_almacenes($es_principal);
        return ApiResponse::success($data);
    }

    /**
     * Obtiene los lotes disponibles para una lista de productos de un almacén.
     */
    public static function get_lotes_disponibles(int $id_almacen, array $ids_productos)
    {
        $data = DataLotesData::get_lotes_disponibles($id_almacen, $ids_productos);
        return ApiResponse::success($data);
    }
}
