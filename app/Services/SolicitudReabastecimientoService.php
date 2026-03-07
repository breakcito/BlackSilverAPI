<?php

namespace App\Services;

use App\Models\SolicitudReabastecimiento;
use App\Models\SolicitudReabastecimientoDetalle;
use App\Shared\Enums\EstadoDetalleRequerimiento;
use App\Shared\Enums\EstadoRequerimiento;
use App\Shared\Enums\Periodo;
use App\Shared\Helpers\CorrelativoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class SolicitudReabastecimientoService
{
    public function get_solicitudes(?int $id_almacen_solicitante = null)
    {
        $data = SolicitudReabastecimiento::get_solicitudes($id_almacen_solicitante);

        return ApiResponse::success($data);
    }

    public function get_detalles_solicitud(int $id_solicitud_reabastecimiento)
    {
        $data = SolicitudReabastecimientoDetalle::get_detalles_solicitud($id_solicitud_reabastecimiento);

        return ApiResponse::success($data);
    }


    public function crear_solicitud(
        int $id_almacen_solicitante,
        int $id_empleado_solicitante,
        string $premura,
        ?string $observacion,
        ?string $fecha_hora_entrega_requerida,
        array $detalles
    ) {
        return DB::transaction(function () use (
            $id_almacen_solicitante,
            $id_empleado_solicitante,
            $premura,
            $observacion,
            $fecha_hora_entrega_requerida,
            $detalles
        ) {
            // 1. Generar Correlativo (Reseteo anual)
            $correlativoData = CorrelativoHelper::generar(
                'solicitud_reabastecimiento',
                'RAB',
                ["id_almacen_solicitante" => $id_almacen_solicitante],
                5,
                Periodo::Anual,
                'created_at'
            );

            // 2. Crear cabecera
            $id_solicitud = SolicitudReabastecimiento::insertGetId([
                'id_almacen_solicitante' => $id_almacen_solicitante,
                'id_empleado_solicitante' => $id_empleado_solicitante,
                'correlativo' => $correlativoData['correlativo'],
                'numero_correlativo' => $correlativoData['numero_correlativo'],
                'observacion' => $observacion,
                'premura' => $premura,
                'fecha_hora_entrega_requerida' => $fecha_hora_entrega_requerida,
                'created_at' => now(),
                'estado' => EstadoRequerimiento::Generada->value,
            ]);

            // 3. Crear detalles
            foreach ($detalles as $detalle) {
                $contenido = (float) $detalle['contenido_por_presentacion'];
                $cantidad = (float) $detalle['cantidad_solicitada'];
                $cantidad_base = $cantidad * $contenido;

                SolicitudReabastecimientoDetalle::insert([
                    'id_solicitud_reabastecimiento' => $id_solicitud,
                    'id_producto' => $detalle['id_producto'],
                    'id_unidad_medida' => $detalle['id_unidad_medida'],
                    'cantidad_solicitada' => $cantidad,
                    'contenido_por_presentacion' => $contenido,
                    'cantidad_solicitada_base' => $cantidad_base,
                    'cantidad_entregada' => 0,
                    'cantidad_entregada_base' => 0,
                    'comentario' => $detalle['comentario'] ?? null,
                    'estado' => EstadoDetalleRequerimiento::Pendiente->value,
                ]);
            }

            return ApiResponse::success(
                SolicitudReabastecimiento::get_solicitudes(id_solicitud_reabastecimiento: $id_solicitud)[0],
                'Solicitud generada correctamente'
            );
        });
    }
}
