<?php

namespace App\Modules\ControlUso\Service;

use App\Models\ActivoFijoUsoLog;
use App\Modules\ControlUso\Data\ControlUsoData;
use App\Shared\Responses\ApiResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Servicio encargado de gestionar la lógica de negocio del módulo Control de Uso.
 */
class ControlUsoService
{
    /**
     * Obtener listado de logs de uso con filtros aplicados.
     */
    public static function get_logs(?string $tipo_control = 'horometro', ?int $mes = null, ?int $anio = null)
    {
        $logs = ControlUsoData::get_logs($tipo_control, $mes, $anio);
        return ApiResponse::success($logs);
    }

    /**
     * Obtener el último valor final del horómetro/odómetro para pre-cargar en el formulario.
     */
    public static function get_ultimo_horometro(int $id_activo_fijo)
    {
        $ultimo = ControlUsoData::get_ultimo_registro($id_activo_fijo);
        $valor = $ultimo ? (float) $ultimo->horometro_fin : 0.0;
        return ApiResponse::success(['ultimo_horometro' => $valor]);
    }

    /**
     * Registrar un nuevo log de uso, realizando los cálculos necesarios en backend.
     */
    public static function registrar_uso(
        int $id_activo_fijo,
        string $fecha_hora_inicio_control,
        ?string $fecha_hora_fin_control = null,
        ?float $horometro_inicio = 0.0,
        ?float $horometro_fin = 0.0,
        ?float $precio_unitario = 0.0,
        ?string $observacion = null
    ) {
        return DB::transaction(function () use (
            $id_activo_fijo,
            $fecha_hora_inicio_control,
            $fecha_hora_fin_control,
            $horometro_inicio,
            $horometro_fin,
            $precio_unitario,
            $observacion
        ) {
            // Parses dates with Carbon
            $fecha_inicio = Carbon::parse($fecha_hora_inicio_control)->toDateTimeString();
            $fecha_fin = $fecha_hora_fin_control ? Carbon::parse($fecha_hora_fin_control)->toDateTimeString() : null;

            // Calculates difference and totals
            $total_horas = 0.0;
            if ($horometro_fin !== null && $horometro_inicio !== null) {
                $total_horas = max(0.0, $horometro_fin - $horometro_inicio);
            }

            $costo_total = $total_horas * ($precio_unitario ?? 0.0);

            // Inserts standard usage log
            $log = ActivoFijoUsoLog::create([
                'id_activo_fijo' => $id_activo_fijo,
                'fecha_hora_inicio_control' => $fecha_inicio,
                'fecha_hora_fin_control' => $fecha_fin,
                'horometro_inicio' => $horometro_inicio,
                'horometro_fin' => $horometro_fin,
                'total_horas' => $total_horas,
                'precio_unitario' => $precio_unitario ?? 0.0,
                'costo_total' => $costo_total,
                'observacion' => $observacion,
                'created_at' => now()->toDateTimeString()
            ]);

            // Update cumulative totals in the activo_fijo record
            $activoInfo = DB::table('activo_fijo')
                ->join('producto', 'producto.id', '=', 'activo_fijo.id_producto')
                ->join('categoria', 'categoria.id', '=', 'producto.id_categoria')
                ->select(
                    'categoria.control_por_horometro',
                    'categoria.control_por_odometro',
                    'categoria.control_por_vueltas'
                )
                ->where('activo_fijo.id', $id_activo_fijo)
                ->first();

            if ($activoInfo) {
                $updates = [];
                if ($activoInfo->control_por_horometro && $horometro_fin !== null) {
                    $updates['total_horas'] = $horometro_fin;
                }
                if ($activoInfo->control_por_odometro && $horometro_fin !== null) {
                    $updates['total_kilometros'] = $horometro_fin;
                }
                if ($activoInfo->control_por_vueltas && $horometro_fin !== null) {
                    $updates['total_vueltas'] = $horometro_fin;
                }

                if (!empty($updates)) {
                    DB::table('activo_fijo')
                        ->where('id', $id_activo_fijo)
                        ->update($updates);
                }
            }

            return ApiResponse::success($log, 'Registro de uso guardado correctamente');
        });
    }
}
