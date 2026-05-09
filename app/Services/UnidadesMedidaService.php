<?php
namespace App\Services;

use App\Data\UnidadesMedidaData;
use App\Shared\Responses\ApiResponse;
class UnidadesMedidaService
{
    /**
     * Listar unidades de meddida
     */
    public static function get_unidades(
        ?int $id_unidad_medida = null,
        ?int $solo_base = null
    ) {
        $unidades = UnidadesMedidaData::get_unidades(
            id_unidad_medida: $id_unidad_medida,
            solo_base: $solo_base
        );

        return ApiResponse::success($unidades);
    }
}