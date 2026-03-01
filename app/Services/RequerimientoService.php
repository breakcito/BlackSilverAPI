<?php

namespace App\Services;

use App\Models\RequerimientoAlmacen;
use App\Models\RequerimientoAlmacenDetalle;
use App\Models\RequerimientoAlmacenDetalleLog;
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
            $correlativoData = CorrelativoHelper::generar('requerimiento_almacen', 'REQ', [], 5, \App\Shared\Enums\Periodo::Anual);
            $nuevo_numero = $correlativoData['numero_correlativo'];
            $correlativo = $correlativoData['correlativo'];

            // 2. Crear Cabecera
            $id_requerimiento = RequerimientoAlmacen::insertGetId([
                'id_usuario_solicitante' => $id_usuario_solicitante,
                'id_mina' => $id_mina,
                'id_almacen_destino' => $id_almacen_destino,
                'correlativo' => $correlativoData['correlativo'],
                'numero_correlativo' => $correlativoData['numero_correlativo'],
                'premura' => $premura,
                'fecha_entrega_requerida' => $fecha_entrega_requerida,
                'created_at' => now(),
                'estado' => \App\Shared\Enums\EstadoRequerimiento::Generada->value,
            ]);

            // 2.1. Asociar Labores (M:N)
            if (! empty($id_labores)) {
                $dataLabores = [];
                foreach ($id_labores as $id_labor) {
                    $dataLabores[] = [
                        'id_requerimiento' => $id_requerimiento,
                        'id_labor' => $id_labor,
                    ];
                }
                \Illuminate\Support\Facades\DB::table('requerimiento_almacen_labor')->insert($dataLabores);
            }

            // 3. Crear Detalle y Logs
            foreach ($detalles as $detalle) {
                // Insertar detalle
                $id_detalle = RequerimientoAlmacenDetalle::insertGetId([
                    'id_requerimiento' => $id_requerimiento,
                    'id_producto' => $detalle['id_producto'],
                    'id_unidad_medida' => $detalle['id_unidad_medida'],
                    'cantidad_solicitada' => $detalle['cantidad_solicitada'],
                    'cantidad_atendida' => 0,
                    'comentario' => $detalle['comentario'] ?? null,
                    'estado' => \App\Shared\Enums\EstadoDetalleRequerimiento::Pendiente->value,
                ]);

                // Registrar log inicial (Pendiente)
                RequerimientoAlmacenDetalleLog::insert([
                    'id_requerimiento_almacen_detalle' => (int) $id_detalle,
                    'id_usuario' => $id_usuario_solicitante,
                    'glosa' => \App\Shared\Enums\EstadoDetalleRequerimiento::Pendiente->getGlosa(),
                    'estado' => \App\Shared\Enums\EstadoDetalleRequerimiento::Pendiente->value,
                    'created_at' => now(),
                ]);
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

        if (! $data) {
            return ApiResponse::error('Requerimiento no encontrado');
        }

        return ApiResponse::success($data);
    }

    public function get_trazabilidad_detalle(int $id_detalle)
    {
        $data = RequerimientoAlmacenDetalleLog::get_trazabilidad($id_detalle);

        return ApiResponse::success($data);
    }
}
