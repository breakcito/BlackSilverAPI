<?php
namespace App\Services;

use App\Data\LotesMineralData;
use App\Shared\Enums\LoteMineral\EstadoLoteMineral;
use App\Shared\Responses\ApiResponse;
class LotesMineralService
{
    /**
     * Listar lotes de mineral.
     */
    public static function get_lotes_mineral(
        ?int $id_lote_mineral = null,
        ?int $id_contratista = null,
        ?int $id_mina = null,
        ?int $id_labor = null,
        ?EstadoLoteMineral $estado = EstadoLoteMineral::EnProduccion
    ) {
        $lotes = LotesMineralData::get_lotes(
            id_lote_mineral: $id_lote_mineral,
            id_contratista: $id_contratista,
            id_mina: $id_mina,
            id_labor: $id_labor,
            estado: $estado
        );

        return ApiResponse::success($lotes);
    }
}