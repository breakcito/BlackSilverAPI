<?php

namespace App\Modules\KardexCarbon\Service;

use App\Modules\KardexCarbon\Data\KardexCarbonData;
use App\Shared\Responses\ApiResponse;

class KardexCarbonService
{
    /**
     * @param array<string, mixed> $opts
     */
    public static function get_movimientos(array $opts = []): array
    {
        $rows = KardexCarbonData::get_movimientos($opts);
        return ApiResponse::success($rows);
    }
}
