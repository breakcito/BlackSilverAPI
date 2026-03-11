<?php

namespace App\Views\RequerimientosAlmacenAtencion\Service;

use App\Models\LoteProducto;
use App\Models\RequerimientoAlmacen;
use App\Models\RequerimientoAlmacenDetalle;
use App\Shared\Enums\Kardex\OrigenMovimiento;
use App\Shared\Enums\Kardex\TipoMovimiento;
use App\Shared\Enums\RequerimientoAlmacen\EstadoDetalleRequerimiento;
use App\Shared\Responses\ApiResponse;
use App\Views\RequerimientosAlmacenAtencion\Data\EntregasData;
use Illuminate\Support\Facades\DB;

class EntregaService
{
    /**
     * Obtiene los lotes disponibles para un producto en un almacén.
     */
    public function obtener_lotes_disponibles(int $id_producto, int $id_almacen)
    {
        $data = EntregasData::get_lotes_disponibles($id_producto, $id_almacen);
        return ApiResponse::success($data);
    }

    /**
     * Obtiene el historial de entregas.
     */
    public function obtener_historial_entregas(int $id_detalle_requerimiento)
    {
        $data = EntregasData::get_historial_entregas(id_detalle_requerimiento: $id_detalle_requerimiento);
        return ApiResponse::success($data);
    }

    /**
     * Registra una entrega física de materiales.
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

            // 1. Validar Stock
            foreach ($detalles as $item) {
                $lote = LoteProducto::find($item['id_lote_producto']);
                if (!$lote || $lote->stock_actual_base < $item['cantidad_base']) {
                    return ApiResponse::error("Stock insuficiente en el lote: " . ($lote->codigo_lote ?? $item['id_lote_producto']));
                }
            }

            // 2. Generar Correlativo
            $requerimiento = RequerimientoAlmacen::find($id_requerimiento);
            $correlativoData = EntregasData::get_nuevo_correlativo($requerimiento->id_almacen_destino);

            // 3. Crear Cabecera de Entrega
            $id_entrega = EntregasData::crear_entrega(
                $id_requerimiento,
                $id_empleado_entrega,
                $id_empleado_recibe,
                $correlativoData['correlativo'],
                $correlativoData['numero_correlativo'],
                strtotime($fecha_entrega), // Asumiendo que espera timestamp segun EntregasData anterior, pero revisemos
                $observacion
            );

            foreach ($detalles as $item) {
                $id_rad = $item['id_requerimiento_almacen_detalle'];
                $id_lote = $item['id_lote_producto'];
                
                // 4. Crear Detalle de Entrega
                EntregasData::insert_entrega_detalle(
                    $id_entrega,
                    $id_rad,
                    $id_lote,
                    $item['cantidad_base'],
                    $item['cantidad_lote'],
                    $item['cantidad_requerimiento']
                );

                // 5. Cargar Lote para Kardex
                $lote = LoteProducto::find($id_lote);
                $stock_anterior = $lote->stock_actual;
                $stock_anterior_base = $lote->stock_actual_base;

                // 6. Actualizar Stock del Lote
                EntregasData::update_lote_stock($id_lote, $item['cantidad_lote'], $item['cantidad_base']);

                // 7. Registrar Kardex (Salida)
                EntregasData::insert_kardex(
                    $id_lote,
                    $id_entrega,
                    OrigenMovimiento::Entrega->value,
                    TipoMovimiento::Salida->value,
                    $stock_anterior,
                    $stock_anterior_base,
                    $item['cantidad_lote'],
                    $item['cantidad_base'],
                    $stock_anterior - $item['cantidad_lote'],
                    $stock_anterior_base - $item['cantidad_base'],
                    "Entrega por Requerimiento ({$correlativoData['correlativo']})"
                );

                // 8. Actualizar Requerimiento Detalle
                $detalle_req = RequerimientoAlmacenDetalle::find($id_rad);
                $ya_entregado_antes = $detalle_req->cantidad_entregada_base;
                
                EntregasData::increment_detalle_entregado($id_rad, $item['cantidad_requerimiento'], $item['cantidad_base']);

                // Reload para ver el nuevo estado
                $detalle_req->refresh();

                // 9. Actualizar Estado del Item
                $finalizo_item = ($detalle_req->cantidad_entregada_base >= $detalle_req->cantidad_solicitada_base);
                $nuevo_estado_item = $finalizo_item ? EstadoDetalleRequerimiento::Completado->value : EstadoDetalleRequerimiento::EnDespacho->value;
                
                EntregasData::update_detalle_estado($id_rad, $nuevo_estado_item, $id_empleado_entrega);

                // 10. Log de Trazabilidad
                if ($ya_entregado_antes == 0) {
                    EntregasData::insert_detalle_log($id_rad, $id_empleado_entrega, EstadoDetalleRequerimiento::EnDespacho->getGlosa(), EstadoDetalleRequerimiento::EnDespacho->value);
                }

                EntregasData::insert_detalle_log($id_rad, $id_empleado_entrega, EstadoDetalleRequerimiento::NuevaEntrega->getGlosa((string)$item['cantidad_requerimiento']), EstadoDetalleRequerimiento::NuevaEntrega->value);

                if ($finalizo_item) {
                    EntregasData::insert_detalle_log($id_rad, $id_empleado_entrega, EstadoDetalleRequerimiento::Completado->getGlosa(), EstadoDetalleRequerimiento::Completado->value);
                }
            }

            // 11. Verificar cierre de requerimiento
            if (EntregasData::check_requerimiento_completado($id_requerimiento)) {
                EntregasData::update_requerimiento_estado($id_requerimiento, 'Cerrado');
            }

            return ApiResponse::success([
                'mensaje' => 'Entrega registrada exitosamente', 
                'id_entrega' => $id_entrega,
                'correlativo' => $correlativoData['correlativo']
            ]);
        });
    }

    /**
     * Obtiene los empleados para la entrega
     */
    public function obtener_empleados()
    {
        $data = EntregasData::get_empleados();
        return ApiResponse::success($data);
    }
}
