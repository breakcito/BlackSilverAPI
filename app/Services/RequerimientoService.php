<?php

namespace App\Modules\RequerimientosAlmacen\Services;

use App\Modules\RequerimientosAlmacen\Models\RequerimientoAlmacen;
use App\Modules\RequerimientosAlmacen\Models\RequerimientoAlmacenDetalle;
use App\Modules\RequerimientosAlmacen\Models\RequerimientoAlmacenLabor;
use App\Modules\RequerimientosAlmacen\Models\RequerimientoAlmacenDetalleLog;
use App\Shared\Helpers\CorrelativoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class RequerimientoService
{
    public function get_requerimientos(
        ?int $id_mina = null,
        ?int $id_almacen_destino = null,
        ?string $estado = null,
        ?string $fecha_inicio = null,
        ?string $fecha_fin = null
    ) {
        $data = RequerimientoAlmacen::get_requerimientos(
            $id_mina,
            $id_almacen_destino,
            $estado,
            $fecha_inicio,
            $fecha_fin
        );

        return ApiResponse::success($data);
    }

    public function crear_requerimiento(
        int $id_usuario_solicitante,
        int $id_mina,
        ?array $id_labores,
        int $id_almacen_destino,
        string $premura,
        ?string $fecha_entrega_requerida,
        array $detalles
    ) {
        return DB::transaction(function () use (
            $id_usuario_solicitante,
            $id_mina,
            $id_labores,
            $id_almacen_destino,
            $premura,
            $fecha_entrega_requerida,
            $detalles
        ) {
            // 1. Generar Correlativo
            $nuevo_numero = CorrelativoHelper::proximoNumero('requerimiento_almacen', 'numero_correlativo', true);
            $correlativo = 'REQ';

            // 2. Crear Cabecera
            $id_requerimiento = RequerimientoAlmacen::crear_requerimiento(
                $id_usuario_solicitante,
                $id_mina,
                $id_almacen_destino,
                $correlativo,
                $nuevo_numero,
                $premura,
                $fecha_entrega_requerida
            );

            // 2.1. Asociar Labores (M:N)
            if (!empty($id_labores)) {
                RequerimientoAlmacenLabor::asociar_labores($id_requerimiento, $id_labores);
            }

            // 3. Crear Detalle y Logs
            foreach ($detalles as $detalle) {
                // Insertar detalle
                $id_detalle = RequerimientoAlmacenDetalle::crear_detalle(
                    $id_requerimiento,
                    $detalle['id_producto'],
                    $detalle['id_unidad_medida'],
                    $detalle['cantidad_solicitada'],
                    $detalle['comentario'] ?? null
                );

                // Registrar log inicial (Pendiente)
                RequerimientoAlmacenDetalleLog::registrar_log(
                    (int)$id_detalle,
                    $id_usuario_solicitante,
                    \App\Shared\Enums\EstadoDetalleRequerimiento::Pendiente
                );
            }

            return ApiResponse::success(
                RequerimientoAlmacen::get_requerimiento_by_id($id_requerimiento),
                'Requerimiento generado correctamente'
            );
        });
    }

    public function get_almacenes_por_mina(int $id_mina)
    {
        $almacenes = DB::table('almacen as a')
            ->join('almacen_mina as am', 'am.id_almacen', '=', 'a.id')
            ->where('am.id_mina', $id_mina)
            ->where('a.estado', 'Activo')
            ->select('a.id', 'a.nombre', 'a.es_principal')
            ->get();

        return ApiResponse::success($almacenes);
    }

    public function get_requerimiento_por_id(int $id)
    {
        $data = RequerimientoAlmacen::get_requerimiento_by_id($id);
        
        if (!$data) {
            return ApiResponse::error('Requerimiento no encontrado', 404);
        }

        return ApiResponse::success($data);
    }

    public function get_trazabilidad_detalle(int $id_detalle)
    {
        $data = RequerimientoAlmacenDetalleLog::get_trazabilidad($id_detalle);
        return ApiResponse::success($data);
    }
}
