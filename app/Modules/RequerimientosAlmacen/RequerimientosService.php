<?php

namespace App\Modules\RequerimientosAlmacen;

use App\Data\UnidadesMedidaData;
use App\Shared\Responses\ApiResponse;
use App\Modules\RequerimientosAlmacen\Data\RequerimientosData;
use App\Modules\RequerimientosAlmacen\Data\RequerimientosDetalleData;
use Illuminate\Support\Facades\DB;

class RequerimientosService
{
    public function get_requerimientos(
        int $id_empleado,
        ?string $mes = null,
        ?string $yearcito = null
    ): array {
        $data = RequerimientosData::get_resumen_requerimientos(
            id_empleado_solicitante: $id_empleado,
            mes: $mes,
            yearcito: $yearcito
        );

        // Adjuntar labores a cada requerimiento
        foreach ($data as $req) {
            $req->labores = RequerimientosData::get_labores_by_requerimiento((int) $req->id_requerimiento);
        }

        return ApiResponse::success($data);
    }

    public function crear_requerimiento(
        int $id_empleado_solicitante,
        int $id_mina,
        int $id_almacen_destino,
        string $premura,
        ?string $fecha_entrega_requerida,
        ?string $observacion,
        ?array $id_labores,
        array $detalles
    ): array {
        return DB::transaction(function () use (
            $id_empleado_solicitante,
            $id_mina,
            $id_almacen_destino,
            $premura,
            $fecha_entrega_requerida,
            $observacion,
            $id_labores,
            $detalles
        ) {
            // 1. Generar Correlativo
            $correlativoData = RequerimientosData::get_nuevo_correlativo($id_almacen_destino);

            // 2. Crear Cabecera
            $id_requerimiento = RequerimientosData::crear_requerimiento(
                $id_empleado_solicitante,
                $id_mina,
                $id_almacen_destino,
                $correlativoData['correlativo'],
                $correlativoData['numero_correlativo'],
                $premura,
                $observacion,
                $fecha_entrega_requerida ?? now()->addDays(2)->toDateString()
            );

            // 3. Asociar Labores
            if (!empty($id_labores)) {
                foreach ($id_labores as $id_labor) {
                    RequerimientosData::asignar_labor($id_requerimiento, $id_labor);
                }
            }

            // 4. Crear Detalles y Trazabilidad
            foreach ($detalles as $detalle) {
                $contenido = (float) $detalle['contenido_por_presentacion'];
                $cantidad = (float) $detalle['cantidad_solicitada'];
                $cantidad_base = $cantidad * $contenido;

                $id_detalle = RequerimientosDetalleData::crear_detalle(
                    $id_requerimiento,
                    $detalle['id_producto'],
                    $detalle['id_unidad_medida'],
                    $cantidad,
                    $contenido,
                    $cantidad_base,
                    $detalle['comentario'] ?? null,
                    $detalle['id_producto_destino'] ?? null
                );

                RequerimientosDetalleData::registrar_trazabilidad($id_detalle, $id_empleado_solicitante);
            }

            $resumen = RequerimientosData::get_requerimiento_by_id($id_requerimiento);
            $resumen->labores = RequerimientosData::get_labores_by_requerimiento($id_requerimiento);

            return ApiResponse::success(
                $resumen,
                'Requerimiento generado correctamente'
            );
        });
    }

    public function get_detalle_by_requerimiento(int $id_requerimiento): array
    {
        $data = RequerimientosDetalleData::get_detalles_by_requerimiento($id_requerimiento);
        return ApiResponse::success($data);
    }

    public function get_trazabilidad_by_detalle(int $id_detalle): array
    {
        $data = RequerimientosDetalleData::get_trazabilidad_by_detalle($id_detalle);
        return ApiResponse::success($data);
    }

    public function get_labores_by_requerimiento(int $id_requerimiento): array
    {
        $data = RequerimientosData::get_labores_by_requerimiento($id_requerimiento);
        return ApiResponse::success($data);
    }

    public function get_data_to_registro(int $id_empleado): array
    {
        $minas = RequerimientosData::get_minas();
        $productos = RequerimientosDetalleData::get_productos();
        $unidades = UnidadesMedidaData::get_unidades();

        return ApiResponse::success([
            'minas' => $minas,
            'productos' => $productos,
            'unidades' => $unidades,
        ]);
    }

    public function get_data_by_mina(int $id_mina): array
    {
        $almacenes = RequerimientosData::get_almacenes_by_mina($id_mina);
        $labores = RequerimientosData::get_labores($id_mina);

        return ApiResponse::success([
            'almacenes' => $almacenes,
            'labores' => $labores,
        ]);
    }
}
