<?php

namespace App\Modules\LotesProductos\Service;

use App\Data\AlmacenesData;
use App\Data\UnidadesMedidaData;
use App\Shared\Responses\ApiResponse;
use App\Modules\LotesProductos\Data\AuxData;

class AuxService
{
    /**
     * Listar almacenes.
     */
    public static function get_almacenes(int $id_empleado)
    {
        $almacenes = AlmacenesData::get_almacenes(id_responsable: $id_empleado);

        return ApiResponse::success($almacenes);
    }

    /**
     * Listar productos
     */
    public static function get_productos()
    {
        $productos = AuxData::get_productos();

        return ApiResponse::success($productos);
    }


    /**
     * Listar unidades de meddida
     */
    public static function get_unidades_medida()
    {
        $unidades = UnidadesMedidaData::get_unidades();

        return ApiResponse::success($unidades);
    }
}
