<?php

namespace App\Services;

use App\Models\AlmacenMina;
use App\Models\LoteProducto;
use App\Models\Producto;
use App\Models\RequerimientoAlmacen;
use App\Models\RequerimientoAlmacenDetalle;
use App\Models\RequerimientoAlmacenDetalleLog;
use App\Models\RequerimientoAlmacenLabor;
use App\Shared\Enums\EstadoDetalleRequerimiento;
use App\Shared\Helpers\CorrelativoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class RequerimientoAlmacenService
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
        int $id_empleado_solicitante,
        int $id_mina,
        ?array $id_labores,
        int $id_almacen_destino,
        string $premura,
        ?string $fecha_entrega_requerida,
        array $detalles
    ) {
        return DB::transaction(function () use (
            $id_empleado_solicitante,
            $id_mina,
            $id_labores,
            $id_almacen_destino,
            $premura,
            $fecha_entrega_requerida,
            $detalles
        ) {
            // 1. Generar Correlativo (Reseteo anual basado en fecha de creación)
            $correlativoData = CorrelativoHelper::generar(
                'requerimiento_almacen', 
                'REQ', 
                [], 
                5, 
                \App\Shared\Enums\Periodo::Anual,
                'created_at'
            );
            
            // 2. Crear Cabecera
            $id_requerimiento = RequerimientoAlmacen::insertGetId([
                'id_empleado_solicitante' => $id_empleado_solicitante,
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
                DB::table('requerimiento_almacen_labor')->insert($dataLabores);
            }

            // 3. Crear Detalle y Logs
            foreach ($detalles as $detalle) {
                $contenido = (float) $detalle['contenido_por_presentacion'];
                $cantidad = (float) $detalle['cantidad_solicitada'];
                $cantidad_base = $cantidad * $contenido;

                // Insertar detalle
                $id_detalle = RequerimientoAlmacenDetalle::insertGetId([
                    'id_requerimiento_almacen' => $id_requerimiento,
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

                // Registrar log inicial (Solicitud)
                RequerimientoAlmacenDetalleLog::insert([
                    'id_requerimiento_almacen_detalle' => (int) $id_detalle,
                    'id_empleado' => $id_empleado_solicitante,
                    'tipo_origen' => 'Solicitud',
                    'descripcion' => EstadoDetalleRequerimiento::Pendiente->getGlosa(),
                    'estado' => EstadoDetalleRequerimiento::Pendiente->value,
                    'created_at' => now(),
                ]);
            }

            return ApiResponse::success(
                RequerimientoAlmacen::get_requerimiento_by_id($id_requerimiento),
                'Requerimiento generado correctamente'
            );
        });
    }

    public function get_requerimiento_por_id(int $id)
    {
        $data = RequerimientoAlmacen::get_requerimiento_by_id($id);

        if (! $data) {
            return ApiResponse::error('Requerimiento no encontrado');
        }

        // 1. Obtener Labores
        $data->labores = RequerimientoAlmacenLabor::get_labores_por_requerimiento($id);

        // 2. Obtener Detalles (Productos)
        $data->detalles = RequerimientoAlmacenDetalle::get_detalles_by_requerimiento($id);

        return ApiResponse::success($data);
    }

    /**
     * Lista requerimientos filtrados por almacén de destino (Atención).
     */
    public function obtener_requerimientos_atencion(int $id_almacen, ?string $estado = null, ?string $mes = null, ?string $anio = null)
    {
        $data = RequerimientoAlmacen::obtener_requerimientos_atencion($id_almacen, $estado, $mes, $anio);
        return ApiResponse::success($data);
    }

    /**
     * Cambia el estado de un producto (Aprobado/Rechazado) y registra en Timeline.
     */
    public function cambiar_estado_detalle(int $id_empleado, int $id_detalle, string $nuevo_estado, ?string $comentario_decision = null)
    {
        return DB::transaction(function () use ($id_empleado, $id_detalle, $nuevo_estado, $comentario_decision) {

            $updateData = [
                'estado' => $nuevo_estado,
                'id_empleado_atencion' => $id_empleado
            ];

            if ($comentario_decision !== null) {
                $updateData['comentario_decision'] = $comentario_decision;
            }

            RequerimientoAlmacenDetalle::where('id', $id_detalle)->update($updateData);

            // Determinar el Enum para el log
            $estadoEnum = EstadoDetalleRequerimiento::from($nuevo_estado);

            RequerimientoAlmacenDetalleLog::insert([
                'id_requerimiento_almacen_detalle' => $id_detalle,
                'id_empleado' => $id_empleado,
                'tipo_origen' => 'Atención',
                'descripcion' => $estadoEnum->getGlosa($comentario_decision),
                'estado' => $estadoEnum->value,
                'created_at' => now(),
            ]);

            return ApiResponse::success(['mensaje' => 'Estado del producto actualizado correctamente']);
        });
    }

    public function get_trazabilidad_detalle(int $id_detalle)
    {
        $data = RequerimientoAlmacenDetalleLog::get_trazabilidad($id_detalle);

        return ApiResponse::success($data);
    }

    public function get_almacenes_por_mina(int $id_mina)
    {
        $data = AlmacenMina::get_almacenes_por_mina($id_mina);

        return ApiResponse::success($data);
    }

    /**
     * Obtiene los productos de un requerimiento listos para ser atendidos,
     * incluyendo los lotes disponibles en el almacén de destino.
     */
    public function get_detalles_para_atencion(int $id_requerimiento)
    {
        $requerimiento = RequerimientoAlmacen::find($id_requerimiento);
        if (!$requerimiento) return ApiResponse::error("Requerimiento no encontrado");

        // Obtenemos los detalles que están aprobados o en proceso de despacho
        $detalles = RequerimientoAlmacenDetalle::get_detalles_para_atencion($id_requerimiento);

        foreach ($detalles as $det) {
            // Por cada producto, buscamos sus lotes en el almacén de destino del requerimiento
            $det->lotes = LoteProducto::obtener_lotes_disponibles($det->id_producto, $requerimiento->id_almacen_destino);
            
            // Re-formatear stock para mantener compatibilidad con el front si es necesario
            // (LoteProducto::obtener_lotes_disponibles ya trae datos, pero aquí el front esperaba un concat específico)
            foreach ($det->lotes as $lote) {
                if (!isset($lote->stock_formateado)) {
                    $lote->stock_formateado = number_format($lote->stock_actual, 2) . " " . $lote->unidad_medida;
                }
            }
        }

        return ApiResponse::success($detalles);
    }
}
