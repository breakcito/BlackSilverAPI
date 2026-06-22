<?php
namespace App\Services;

use App\Data\AreasData;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Responses\ApiResponse;

class AreasService
{
    /**
     * Listar areas
     */
    public static function get_areas(
        ?int $id_area = null,
        ?EstadoBase $estado = null,
    ) {
        $data = AreasData::get_areas(
            id_area: $id_area,
            estado: $estado
        );

        return ApiResponse::success($data);
    }
}
