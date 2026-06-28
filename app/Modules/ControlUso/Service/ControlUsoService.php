<?php

namespace App\Modules\ControlUso\Service;

use App\Models\ControlUsoActivo;
use App\Models\TarifaUsoActivo;
use App\Models\TipoMaterial;
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

    public static function get_ultimo_odometro(int $id_activo_fijo)
    {
        $ultimo = ControlUsoData::get_ultimo_registro_odometro($id_activo_fijo);
        $valor = $ultimo ? (float) $ultimo->odometro_fin : 0.0;
        return ApiResponse::success(['ultimo_odometro' => $valor]);
    }

    /**
     * Registrar un nuevo log de uso, realizando los cálculos necesarios en backend.
     */
    public static function registrar_uso(
        int $id_activo_fijo,
        string $fecha_hora_inicio_control,
        ?string $fecha_hora_fin_control = null,
        ?float $horometro_inicio = null,
        ?float $horometro_fin = null,
        ?float $odometro_inicio = null,
        ?float $odometro_fin = null,
        ?int $cantidad_vueltas = null,
        ?int $cantidad_sacos = null,
        ?int $id_tarifa = null,
        ?float $precio_unitario = 0.0,
        ?bool $es_para_mina = null,
        ?int $id_mina = null,
        ?int $id_labor = null,
        ?int $id_cliente = null,
        ?string $tipo_carga = null,
        ?string $observacion = null
    ) {
        return DB::transaction(function () use ($id_activo_fijo, $fecha_hora_inicio_control, $fecha_hora_fin_control, $horometro_inicio, $horometro_fin, $odometro_inicio, $odometro_fin, $cantidad_vueltas, $cantidad_sacos, $id_tarifa, $precio_unitario, $es_para_mina, $id_mina, $id_labor, $id_cliente, $tipo_carga, $observacion) {
            // Parses dates with Carbon
            $fecha_inicio = Carbon::parse($fecha_hora_inicio_control)->toDateTimeString();
            $fecha_fin = $fecha_hora_fin_control ? Carbon::parse($fecha_hora_fin_control)->toDateTimeString() : null;

            // Calculates difference and totals
            $total_horas = 0.0;
            $costo_total = 0.0;

            if ($horometro_fin !== null && $horometro_inicio !== null) {
                $total_horas = max(0.0, $horometro_fin - $horometro_inicio);
                $costo_total = $total_horas * ($precio_unitario ?? 0.0);
            } elseif ($odometro_fin !== null && $odometro_inicio !== null) {
                $total_km = max(0.0, $odometro_fin - $odometro_inicio);
                $costo_total = $total_km * ($precio_unitario ?? 0.0);
            } elseif ($cantidad_vueltas !== null) {
                $costo_total = $cantidad_vueltas * ($precio_unitario ?? 0.0);
            }

            // Inserts standard usage log
            $log = ControlUsoActivo::create([
                'id_activo_fijo' => $id_activo_fijo,
                'fecha_hora_inicio_control' => $fecha_inicio,
                'fecha_hora_fin_control' => $fecha_fin,
                'horometro_inicio' => $horometro_inicio,
                'horometro_fin' => $horometro_fin,
                'odometro_inicio' => $odometro_inicio,
                'odometro_fin' => $odometro_fin,
                'cantidad_vueltas' => $cantidad_vueltas,
                'cantidad_sacos' => $cantidad_sacos,
                'total_horas' => $total_horas,
                'precio_unitario' => $precio_unitario ?? 0.0,
                'costo_total' => $costo_total,
                'es_para_mina' => $es_para_mina,
                'id_mina' => $id_mina,
                'id_labor' => $id_labor,
                'id_cliente' => $id_cliente,
                'tipo_carga' => $tipo_carga,
                'id_tarifa' => $id_tarifa,
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
                if ($activoInfo->control_por_odometro && $odometro_fin !== null) {
                    $updates['total_kilometros'] = $odometro_fin;
                }
                if ($activoInfo->control_por_vueltas && $cantidad_vueltas !== null) {
                    // Get current and add
                    $curr = DB::table('activo_fijo')->where('id', $id_activo_fijo)->value('total_vueltas') ?? 0;
                    $updates['total_vueltas'] = $curr + $cantidad_vueltas;
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

    public static function get_tarifas(int $id_activo_fijo)
    {
        $res = ControlUsoData::get_tarifas($id_activo_fijo);
        return ApiResponse::success($res);
    }

    public static function crear_tarifa(
        int $id_activo_fijo,
        string $tipo_control,
        float $precio_unitario,
        string $descripcion,
        ?int $id_tipo_material,
        ?int $distancia_metros = null
    ) {
        $tarifa = TarifaUsoActivo::create([
            'id_activo_fijo' => $id_activo_fijo,
            'tipo_control' => $tipo_control,
            'precio_unitario' => $precio_unitario,
            'descripcion' => $descripcion,
            'id_tipo_material' => $id_tipo_material,
            'distancia_metros' => $distancia_metros,
            'created_at' => now()->toDateTimeString()
        ]);
        return ApiResponse::success($tarifa, 'Tarifa registrada exitosamente');
    }

    public static function get_materiales()
    {
        $res = ControlUsoData::get_materiales();
        return ApiResponse::success($res);
    }

    public static function crear_material(string $nombre)
    {
        $material = TipoMaterial::create([
            'nombre' => $nombre,
            'created_at' => now()->toDateTimeString()
        ]);
        return ApiResponse::success($material, 'Material registrado exitosamente');
    }
}
