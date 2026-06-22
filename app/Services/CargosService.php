<?php
namespace App\Services;

use App\Data\CargosData;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Responses\ApiResponse;

class CargosService
{
    /**
     * Listar cargos
     */
    public static function get_cargos(
        ?int $id_cargo = null,
        ?int $id_area = null,
        ?EstadoBase $estado = null,
    ) {
        $data = CargosData::get_cargos(
            id_cargo: $id_cargo,
            id_area: $id_area,
            estado: $estado
        );

        return ApiResponse::success($data);
    }
}
