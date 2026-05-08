<?php

namespace App\Modules\Productos\Service;

use App\Data\UnidadesMedidaData;
use App\Modules\Productos\Data\AuxData;
use App\Shared\Responses\ApiResponse;

class AuxService
{

    /**
     * Obtener unidades de medida base
     */
    public static function get_unidades_medida()
    {
        return ApiResponse::success(UnidadesMedidaData::get_unidades());
    }

    /**
     * Obtener categorías de tipo bien
     */
    public static function get_categorias()
    {
        return ApiResponse::success(AuxData::get_categorias());
    }

}
