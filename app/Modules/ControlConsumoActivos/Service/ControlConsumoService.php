<?php

namespace App\Modules\ControlConsumoActivos\Service;

use App\Modules\ControlConsumoActivos\Data\ControlConsumoData;
use App\Shared\Responses\ApiResponse;

class ControlConsumoService
{
    public static function get_reporte(
        int $id_activo_fijo,
        int $mes,
        int $yearcito
    ): array {
        return ApiResponse::success(ControlConsumoData::get_reporte(
            $id_activo_fijo,
            $mes,
            $yearcito
        ));
    }
}
