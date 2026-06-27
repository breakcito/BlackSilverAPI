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

    public static function registrar_lote(
        int $id_contratista,
        int $id_mina,
        ?int $id_labor,
        int $id_empleado_registro,
        ?string $codigo_interno = null,
        ?string $descripcion = null,
        ?string $fecha_inicio_produccion = null
    ) {
        return DB::transaction(function () use (
            $id_contratista,
            $id_mina,
            $id_labor,
            $id_empleado_registro,
            $descripcion,
            $fecha_inicio_produccion
        ) {
            // Generar correlativo
            $nuevo_correlativo = LoteMineralData::get_nuevo_correlativo();
            $correlativo = $nuevo_correlativo['correlativo'];
            $numero_correlativo = $nuevo_correlativo['numero_correlativo'];

            // Obtener prefijo de la labor para generar el código interno
            $prefijo = null;
            if ($id_labor) {
                $prefijo = DB::table('labor')->where('id', $id_labor)->value('prefijo');
            }

            $codigo_interno = null;
            if ($prefijo && $fecha_inicio_produccion) {
                $codigo_interno = strtoupper($prefijo) . '-' . date('dmy', strtotime($fecha_inicio_produccion));
            }

            // Calcular estado inicial
            $estado = \App\Shared\Enums\LoteMineral\EstadoLoteMineral::Pendiente->value;
            if ($fecha_inicio_produccion) {
                $today = date('Y-m-d');
                $fecha_db = date('Y-m-d', strtotime($fecha_inicio_produccion));
                if ($fecha_db <= $today) {
                    $estado = \App\Shared\Enums\LoteMineral\EstadoLoteMineral::EnProduccion->value;
                }
            }

            // Registrar lote
            $id_lote = LoteMineralData::registrar_lote(
                $id_contratista,
                $id_mina,
                $id_labor,
                $id_empleado_registro,
                $codigo_interno,
                $descripcion,
                $correlativo,
                $numero_correlativo,
                $fecha_inicio_produccion,
                $estado
            );

            $lote = LoteMineralData::get_lote_by_id($id_lote);

            return ApiResponse::success($lote, 'Lote de mineral registrado correctamente');
        });
    }
}
