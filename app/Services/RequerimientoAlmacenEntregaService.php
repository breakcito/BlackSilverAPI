<?php

namespace App\Services;

use App\Models\KardexProducto;
use App\Models\LoteProducto;
use App\Models\RequerimientoAlmacen;
use App\Models\RequerimientoAlmacenDetalle;
use App\Models\RequerimientoAlmacenDetalleLog;
use App\Models\RequerimientoAlmacenEntrega;
use App\Models\RequerimientoAlmacenEntregaDetalle;
use App\Shared\Enums\OrigenMovimiento;
use App\Shared\Enums\EstadoDetalleRequerimiento;
use App\Shared\Enums\TipoMovimiento;
use App\Shared\Helpers\CorrelativoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class RequerimientoAlmacenEntregaService
{
    /**
     * Registra una entrega física de materiales (Despacho).
     */
    public function registrar_entrega(
        int $id_empleado_entrega,
        int $id_requerimiento,
        int $id_empleado_recibe,
        string $fecha_entrega,
        ?string $observacion,
        array $detalles
    ) {
        return DB::transaction(function () use ($id_empleado_entrega, $id_requerimiento, $id_empleado_recibe, $fecha_entrega, $observacion, $detalles) {

            // 1. Validar Stock de todos los lotes involucrados antes de empezar
            foreach ($detalles as $item) {
                $lote = LoteProducto::find($item['id_lote_producto']);
                if (!$lote || $lote->stock_actual_base < $item['cantidad_base']) {
                    return ApiResponse::error("Stock insuficiente en el lote: " . ($lote->codigo_lote ?? $item['id_lote_producto']));
                }
            }

            // 2. Generar Correlativo de Entrega
            $correlativoData = CorrelativoHelper::generar('requerimiento_almacen_entrega', 'ENT');

            // 3. Crear Cabecera de Entrega
            $entrega = RequerimientoAlmacenEntrega::create([
                'id_requerimiento_almacen' => $id_requerimiento,
                'id_empleado_entrega' => $id_empleado_entrega,
                'id_empleado_recibe' => $id_empleado_recibe,
                'correlativo' => $correlativoData['correlativo'],
                'numero_correlativo' => $correlativoData['numero_correlativo'],
                'fecha_hora_entrega' => $fecha_entrega,
                'observacion' => $observacion,
                'created_at' => now(),
                'estado' => 'Procesado'
            ]);

            foreach ($detalles as $item) {
                $id_rad = $item['id_requerimiento_almacen_detalle'];
                $id_lote = $item['id_lote_producto'];
                
                // 4. Crear Detalle de Entrega
                RequerimientoAlmacenEntregaDetalle::create([
                    'id_requerimiento_almacen_entrega' => $entrega->id,
                    'id_requerimiento_almacen_detalle' => $id_rad,
                    'id_lote_producto' => $id_lote,
                    'cantidad_base' => $item['cantidad_base'],
                    'cantidad_lote' => $item['cantidad_lote'],
                    'cantidad_requerimiento' => $item['cantidad_requerimiento'],
                    'created_at' => now(),
                    'estado' => 'Entregado'
                ]);

                // 5. Cargar Lote para Kardex y Stock
                $lote = LoteProducto::find($id_lote);
                $stock_anterior = $lote->stock_actual;
                $stock_anterior_base = $lote->stock_actual_base;

                // 6. Actualizar Stock del Lote
                $lote->update([
                    'stock_actual' => $stock_anterior - $item['cantidad_lote'],
                    'stock_actual_base' => $stock_anterior_base - $item['cantidad_base']
                ]);

                // 7. Registrar Kardex (Salida)
                KardexProducto::create([
                    'id_lote_producto' => $id_lote,
                    'id_origen' => $entrega->id,
                    'tipo_origen' => OrigenMovimiento::Entrega->value,
                    'tipo_movimiento' => TipoMovimiento::Salida->value,
                    'stock_anterior' => $stock_anterior,
                    'stock_anterior_base' => $stock_anterior_base,
                    'cantidad_movimiento' => $item['cantidad_lote'],
                    'cantidad_movimiento_base' => $item['cantidad_base'],
                    'stock_resultante' => $lote->stock_actual,
                    'stock_resultante_base' => $lote->stock_actual_base,
                    'descripcion' => "Entrega por Requerimiento ({$correlativoData['correlativo']})",
                    'created_at' => now(),
                ]);

                // 8. Actualizar Requerimiento Detalle (Cantidades Atendidas)
                $detalle_req = RequerimientoAlmacenDetalle::find($id_rad);
                $ya_entregado_antes = $detalle_req->cantidad_entregada_base;
                
                $detalle_req->increment('cantidad_entregada', $item['cantidad_requerimiento']);
                $detalle_req->increment('cantidad_entregada_base', $item['cantidad_base']);

                // 9. Actualizar Estado del Item
                $finalizo_item = ($detalle_req->cantidad_entregada_base >= $detalle_req->cantidad_solicitada_base);
                $nuevo_estado_item = $finalizo_item ? EstadoDetalleRequerimiento::Completado->value : EstadoDetalleRequerimiento::DespachoIniciado->value;
                
                $detalle_req->update(['estado' => $nuevo_estado_item]);

                // 10. Log de Trazabilidad (Timeline)
                
                // 10.1. Si es la primera entrega de este ítem, marcar el inicio del proceso
                if ($ya_entregado_antes == 0) {
                    RequerimientoAlmacenDetalleLog::insert([
                        'id_requerimiento_almacen_detalle' => $id_rad,
                        'id_empleado' => $id_empleado_entrega,
                        'tipo_origen' => 'Entrega',
                        'descripcion' => EstadoDetalleRequerimiento::DespachoIniciado->getGlosa(),
                        'estado' => EstadoDetalleRequerimiento::DespachoIniciado->value,
                        'created_at' => now()
                    ]);
                }

                // 10.2. Registro del evento de entrega actual
                RequerimientoAlmacenDetalleLog::insert([
                    'id_requerimiento_almacen_detalle' => $id_rad,
                    'id_empleado' => $id_empleado_entrega,
                    'tipo_origen' => 'Entrega',
                    'descripcion' => EstadoDetalleRequerimiento::NuevaEntrega->getGlosa((string)$item['cantidad_requerimiento']),
                    'estado' => EstadoDetalleRequerimiento::NuevaEntrega->value,
                    'created_at' => now()
                ]);

                // 10.3. Si el ítem se completó con esta entrega, registrar el hito final
                if ($finalizo_item) {
                    RequerimientoAlmacenDetalleLog::insert([
                        'id_requerimiento_almacen_detalle' => $id_rad,
                        'id_empleado' => $id_empleado_entrega,
                        'tipo_origen' => 'Entrega',
                        'descripcion' => EstadoDetalleRequerimiento::Completado->getGlosa(),
                        'estado' => EstadoDetalleRequerimiento::Completado->value,
                        'created_at' => now()
                    ]);
                }
            }

            // 11. Verificar si todo el requerimiento está completado para cerrarlo
            $pendientes = RequerimientoAlmacenDetalle::where('id_requerimiento_almacen', $id_requerimiento)
                ->whereNotIn('estado', ['Completado', 'Cerrado', 'Rechazado - Logística'])
                ->count();

            if ($pendientes === 0) {
                RequerimientoAlmacen::where('id', $id_requerimiento)->update(['estado' => 'Cerrada']);
            }

            return ApiResponse::success([
                'mensaje' => 'Entrega registrada exitosamente', 
                'id_entrega' => $entrega->id,
                'correlativo' => $entrega->correlativo
            ]);
        });
    }

    /**
     * Obtiene el historial de entregas realizadas para un ítem específico.
     */
    public function obtener_historial_entregas_por_item(int $id_detalle)
    {
        // Esta lógica suele ir en el modelo RequerimientoAlmacenEntrega para ser reutilizable
        $historial = DB::select("
            SELECT 
                rae.id AS id_entrega,
                rae.correlativo AS codigo_entrega,
                rae.fecha_hora_entrega AS fecha_entrega,
                CONCAT(er.nombre, ' ', er.apellido) AS entregado_a,
                raed.cantidad_base AS cantidad,
                CONCAT(ee.nombre, ' ', ee.apellido) AS usuario_entrega
            FROM requerimiento_almacen_entrega_detalle raed
            INNER JOIN requerimiento_almacen_entrega rae ON rae.id = raed.id_requerimiento_almacen_entrega
            INNER JOIN empleado ee ON ee.id = rae.id_empleado_entrega
            INNER JOIN empleado er ON er.id = rae.id_empleado_recibe
            WHERE raed.id_requerimiento_almacen_detalle = :id_detalle
            ORDER BY rae.fecha_hora_entrega DESC
        ", ['id_detalle' => $id_detalle]);

        return ApiResponse::success($historial);
    }
}
