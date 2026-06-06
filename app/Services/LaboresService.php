<?php

namespace App\Services;

use App\Data\LaboresData;
use App\Shared\Responses\ApiResponse;

class LaboresService
{
    /**
     * Obtener listado simple de labores.
     */
    public static function get_labores(
        ?int $id_mina = null,
        ?int $id_labor = null,
        ?int $id_requerimiento = null
    ) {
        $labores = LaboresData::get_labores(
            id_mina: $id_mina,
            id_labor: $id_labor,
            id_requerimiento: $id_requerimiento
        );

        return ApiResponse::success($labores);
    }
}
