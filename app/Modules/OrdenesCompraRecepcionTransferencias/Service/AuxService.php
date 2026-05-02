<?php

namespace App\Modules\OrdenesCompraRecepcionTransferencias\Service;

use App\Data\AlmacenesData;
use App\Data\LotesProductosData;
use App\Shared\Responses\ApiResponse;

class AuxService
{
    /**
     * Obtiene los almacenes donde el empleado es responsable
     */
    public static function get_almacenes_autorizados(int $id_empleado)
    {
        $data = AlmacenesData::get_almacenes(id_responsable: $id_empleado);
        return ApiResponse::success($data);
    }

    /**
     * Obtiene los lotes disponibles de varios productos en un almacén
     */
    public static function get_lotes_disponibles(int $id_almacen, array $ids_productos)
    {
        $data = LotesProductosData::get_lotes_disponibles($id_almacen, $ids_productos);
        return ApiResponse::success($data);
    }
}
