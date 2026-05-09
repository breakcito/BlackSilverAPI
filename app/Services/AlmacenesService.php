<?php
namespace App\Services;

use App\Data\AlmacenesData;
use App\Shared\Responses\ApiResponse;
class AlmacenesService
{
    /**
     * Listar almacenes.
     */
    public static function get_almacenes(
        ?int $id_almacen = null,
        ?int $id_responsable = null,
        ?int $es_principal = null
    ) {
        $almacenes = AlmacenesData::get_almacenes(
            id_almacen: $id_almacen,
            id_responsable: $id_responsable,
            es_principal: $es_principal
        );

        return ApiResponse::success($almacenes);
    }
}