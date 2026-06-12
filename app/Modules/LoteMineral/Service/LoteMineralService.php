<?php

namespace App\Modules\LoteMineral\Service;

use App\Modules\LoteMineral\Data\LoteMineralData;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class LoteMineralService
{
    /**
     * Obtener listado de lotes.
     */
    public static function get_lotes(?int $mes = null, ?int $anio = null)
    {
        $lotes = LoteMineralData::get_lotes($mes, $anio);
        return ApiResponse::success($lotes);
    }

    /**
     * Registrar un nuevo lote de mineral.
     */
    public static function registrar_lote(
        int $id_contratista,
        int $id_mina,
        ?int $id_labor,
        int $id_empleado_registro,
        string $codigo_interno,
        ?string $descripcion
    ) {
        return DB::transaction(function () use (
            $id_contratista,
            $id_mina,
            $id_labor,
            $id_empleado_registro,
            $codigo_interno,
            $descripcion
        ) {
            // Generar correlativo
            $nuevo_correlativo = LoteMineralData::get_nuevo_correlativo();
            $correlativo = $nuevo_correlativo['correlativo'];
            $numero_correlativo = $nuevo_correlativo['numero_correlativo'];

            // Registrar lote
            $id_lote = LoteMineralData::registrar_lote(
                $id_contratista,
                $id_mina,
                $id_labor,
                $id_empleado_registro,
                $codigo_interno,
                $descripcion,
                $correlativo,
                $numero_correlativo
            );

            $lote = LoteMineralData::get_lote_by_id($id_lote);

            return ApiResponse::success($lote, 'Lote de mineral registrado correctamente');
        });
    }
}
