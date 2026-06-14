<?php

namespace App\Modules\ProduccionMineral\Service;

use App\Models\LoteMineral;
use App\Modules\ProduccionMineral\Data\ProduccionData;
use App\Shared\Enums\LoteMineral\EstadoLoteMineral;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class ProduccionService
{
    /**
     * Iniciar el proceso de producción de un lote mineral.
     */
    public static function iniciar_produccion(int $id_lote_mineral): array
    {
        return DB::transaction(function () use ($id_lote_mineral) {
            $lote = LoteMineral::where('id', $id_lote_mineral)->first();

            if (!$lote) {
                return ApiResponse::error('El lote mineral no existe.');
            }

            if ($lote->estado !== EstadoLoteMineral::Pendiente->value) {
                return ApiResponse::error('El lote mineral debe estar en estado Pendiente para poder iniciar producción.');
            }

            $success = ProduccionData::iniciar_produccion($id_lote_mineral);

            if (!$success) {
                return ApiResponse::error('No se pudo iniciar el proceso de producción.');
            }

            return ApiResponse::success(null, 'Proceso de producción iniciado correctamente.');
        });
    }

    /**
     * Obtener un listado de los lotes en producción junto a su resumen de consumos.
     */
    public static function get_resumen(): array
    {
        $lotes = ProduccionData::get_lotes_en_produccion();

        foreach ($lotes as $lote) {
            $lote->consumos = ProduccionData::get_consumos_by_lote_mineral((int) $lote->id_lote_mineral);
        }

        return ApiResponse::success($lotes);
    }
}
