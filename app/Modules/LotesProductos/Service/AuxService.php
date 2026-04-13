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
    public static function get_almacenes(int $id_usuario, int $id_empleado)
    {
        // Verificar si el usuario puede ver todos los almacenes
        $puede_ver_todos = AuxData::puede_ver_almacenes_all($id_usuario);

        // si puede ver todos, no filtramos nada
        // si NO puede ver todos, filtramos por los almacenes donde es responsable
        $almacenes = AlmacenesData::get_almacenes(id_responsable: $puede_ver_todos ? null : $id_empleado);

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
