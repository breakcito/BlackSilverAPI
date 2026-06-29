<?php

namespace App\Modules\LoteMineral\Service;

use App\Modules\LoteMineral\Data\LoteMineralData;
use App\Shared\Enums\LoteMineral\EstadoLoteMineral;
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
     * Registrar un nuevo lote de mineral para un contratista en una labor.
     */
    public static function registrar_lote(
        int $id_contratista,
        int $id_mina,
        int $id_labor,
        int $id_empleado_registro,
        ?string $descripcion = null,
        ?string $fecha_inicio_produccion = null
    ) {
        return DB::transaction(function () use ($id_contratista, $id_mina, $id_labor, $id_empleado_registro, $descripcion, $fecha_inicio_produccion) {
            // Obtener prefijo de la labor para generar el código interno
            $prefijo = null;
            if ($id_labor) {
                $prefijo = LoteMineralData::get_prefijo_labor($id_labor);
            }

            $codigo = null;
            if ($prefijo && $fecha_inicio_produccion) {
                $codigo = strtoupper($prefijo) . '-' . date('dmy', strtotime($fecha_inicio_produccion));
            }

            // Calcular estado inicial
            $estado = EstadoLoteMineral::Pendiente;
            if ($fecha_inicio_produccion) {
                $today = date('Y-m-d');
                $fecha_db = date('Y-m-d', strtotime($fecha_inicio_produccion));
                if ($fecha_db <= $today) {
                    $estado = EstadoLoteMineral::EnProduccion;
                }
            }

            // Registrar lote
            $id_lote = LoteMineralData::registrar_lote(
                $id_contratista,
                $id_mina,
                $id_labor,
                $id_empleado_registro,
                $codigo,
                $descripcion,
                $fecha_inicio_produccion,
                $estado
            );

            $lote = LoteMineralData::get_lotes(id_lote_mineral: $id_lote);

            return ApiResponse::success($lote, 'Lote de mineral registrado correctamente');
        });
    }
}
