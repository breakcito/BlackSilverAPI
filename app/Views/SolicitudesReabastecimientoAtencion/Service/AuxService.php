<?php

namespace App\Views\SolicitudesReabastecimientoAtencion\Service;

use App\Data\AlmacenesData;
use App\Data\EmpleadosData;
use App\Data\LotesProductosData;
use App\Shared\Responses\ApiResponse;

class AuxService
{
    /**
     * Obtiene los almacenes 
     */
    public static function get_almacenes(bool $es_principal = false)
    {
        $data = AlmacenesData::get_almacenes(es_principal: $es_principal ? 1 : 0);
        return ApiResponse::success($data);
    }

    /**
     * Obtiene los empleados para la entrega
     */
    public static function get_empleados()
    {
        $data = EmpleadosData::get_empleados();
        return ApiResponse::success($data);
    }

    /**
     * Obtiene los lotes disponibles para una lista de productos de un almacén.
     */
    public static function get_lotes_disponibles(int $id_almacen, array $ids_productos)
    {
        $data = LotesProductosData::get_lotes_disponibles($id_almacen, $ids_productos);
        return ApiResponse::success($data);
    }
}
