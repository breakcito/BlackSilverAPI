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
    ) {
        $unidades = UnidadesMedidaData::get_unidades(
            id_unidad_medida: $id_unidad_medida,
        );

        return ApiResponse::success($unidades);
    }
}