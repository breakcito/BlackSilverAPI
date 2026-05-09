<?php

namespace App\Modules\Productos\Service;

use App\Modules\Productos\Data\AuxData;
use App\Shared\Responses\ApiResponse;

class AuxService
{
    /**
     * Obtener categorías de tipo bien
     */
    public static function get_categorias()
    {
        return ApiResponse::success(AuxData::get_categorias());
    }

}
