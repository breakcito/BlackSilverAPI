<?php

namespace App\Views\SolicitudesReabastecimientoAtencion\Service;

use App\Shared\Responses\ApiResponse;
use App\Views\SolicitudesReabastecimientoAtencion\Data\AuxData;

class AuxService
{
    /**
     * Obtiene los almacenes 
     */
    public static function get_almacenes(bool $es_principal = false)
    {
        $data = AuxData::get_almacenes($es_principal ? 1 : 0);
        return ApiResponse::success($data);
    }

    /**
     * Obtiene los empleados para la entrega
     */
    public static function get_empleados()
    {
        $data = AuxData::get_empleados();
        return ApiResponse::success($data);
    }

    /**
     * Obtiene los lotes disponibles para una lista de productos de un almacén.
     */
    public static function get_lotes_disponibles(array $ids_productos, int $id_almacen)
    {
        $data = AuxData::get_lotes_disponibles($ids_productos, $id_almacen);
        return ApiResponse::success($data);
    }
}
