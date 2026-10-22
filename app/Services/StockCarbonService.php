<?php

namespace App\Services;

use App\Data\StockCarbonData;
use App\Shared\Responses\ApiResponse;

class StockCarbonService
{
    /**
     * Stock de carbon disponible por almacen, tipo y calidad.
     */
    public static function get_stocks(array $opts = [])
    {
        return ApiResponse::success(StockCarbonData::get_stocks($opts));
    }
}
