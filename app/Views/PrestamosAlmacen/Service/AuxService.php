<?php

namespace App\Views\PrestamosAlmacen\Service;

use App\Shared\Responses\ApiResponse;
use App\Views\PrestamosAlmacen\Data\AuxData;

class AuxService
{
    /**
     * Obtiene los almacenes secundarios
     */
    public static function get_almacenes_secundarios()
    {
        $data = AuxData::get_almacenes_secundarios();
        return ApiResponse::success($data);
    }
}
