<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Service;

use App\Data\EmpleadosData;
use App\Data\KardexProductosData;
use App\Data\LotesProductosData;
use App\Shared\Enums\Kardex\OrigenMovimiento;
use App\Shared\Enums\Kardex\TipoMovimiento;
use App\Shared\Enums\RequerimientoAlmacen\EstadoDetalleRequerimiento;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use App\Modules\RequerimientosAlmacenAtencion\Data\EntregasData;
use App\Modules\RequerimientosAlmacenAtencion\Data\EntregasDetalleData;
use App\Modules\RequerimientosAlmacenAtencion\Data\RequerimientosData;
use App\Modules\RequerimientosAlmacenAtencion\Data\RequerimientosDetalleData;
use Illuminate\Support\Facades\DB;

class EntregaService
{
    /**
     * Obtiene los lotes disponibles para una lista de productos de un almacén.
     */
    public function obtener_lotes_disponibles(int $id_almacen, array $ids_productos)
    {
        $data = LotesProductosData::get_lotes_disponibles($id_almacen, $ids_productos);
        return ApiResponse::success($data);
    }

    /**
     * Obtiene el historial de entregas y sus detalles.
     */
    public function obtener_historial_entregas(int $id_requerimiento)
    {
        $data = EntregasData::get_historial_entregas(id_requerimiento: $id_requerimiento);

        foreach ($data as $entrega) {
            $entrega->evidencias = $entrega->evidencias ? json_decode($entrega->evidencias) : null;
            $entrega->detalles = EntregasDetalleData::get_detalles_entrega(id_entrega: (int) $entrega->id_requerimiento_almacen_entrega);
        }

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
        ?array $evidencias, // archivos
        array $detalles // {id_requerimiento_almacen_detalle, id_lote_producto, cantidad_base, cantidad_lote, cantidad_requerimiento}
    ) {
        return DB::transaction(function () use ($id_empleado_entrega, $id_requerimiento, $id_empleado_recibe, $fecha_entrega, $observacion, $evidencias, $detalles) {

            // Procesar Evidencias si existen
            $evidenciasData = null;
            if (!empty($evidencias)) {
                $evidenciasData = ArchivoHelper::guardarArchivos('requerimientos_almacen_entregas', $evidencias);
            }

            // Pre-cargar todos los lotes en una sola consulta
            $ids_lotes = array_map(fn($i) => (int) $i['id_lote_producto'], $detalles);
            $lotesMap = collect(LotesProductosData::get_lote_simple_by_id($ids_lotes))
                ->keyBy('id_lote');

            // Validar Stock
            foreach ($detalles as $item) {
                $lote = $lotesMap->get((int) $item['id_lote_producto']);
                if (!$lote || $lote['stock_actual_base'] < $item['cantidad_base']) {
                    return ApiResponse::error("Stock insuficiente en el lote: " . ($lote['correlativo'] ?? 'ID: ' . $item['id_lote_producto']));
                }
            }

            // Generar Correlativo
            $requerimiento = RequerimientosData::get_almacen_destino_by_requerimiento($id_requerimiento);
            $correlativoData = EntregasData::get_nuevo_correlativo($requerimiento->id_almacen_destino);

            // Crear Cabecera de Entrega
            $id_entrega = EntregasData::crear_entrega(
                $id_requerimiento,
                $id_empleado_entrega,
                $id_empleado_recibe,
                $correlativoData['correlativo'],
                $correlativoData['numero_correlativo'],
                $fecha_entrega,
                $observacion,
                $evidenciasData,
            );

            foreach ($detalles as $item) {
                $id_rad = $item['id_requerimiento_almacen_detalle'];
                $id_lote = $item['id_lote_producto'];

                // Crear Detalle de Entrega
                $id_detalle_entrega = EntregasDetalleData::crear_detalle_entrega(
                    $id_entrega,
                    $id_rad,
                    $id_lote,
                    $item['cantidad_base'],
                    $item['cantidad_lote'],
                    $item['cantidad_requerimiento']
                );

                // Obtener lote desde el mapa pre-cargado
                $lote = $lotesMap->get((int) $id_lote);
                $stock_anterior = $lote['stock_actual'];
                $stock_anterior_base = $lote['stock_actual_base'];
                $nuevo_stock = $stock_anterior - $item['cantidad_lote'];
                $nuevo_stock_base = $stock_anterior_base - $item['cantidad_base'];

                // Actualizar Stock del Lote
                LotesProductosData::update_stock($id_lote, $nuevo_stock, $nuevo_stock_base);

                // Registrar Kardex (Salida)
                KardexProductosData::registrar_kardex(
                    id_lote: $id_lote,
                    id_origen: $id_detalle_entrega,
                    tipo_movimiento: TipoMovimiento::Salida,
                    tipo_origen: OrigenMovimiento::Entrega,
                    descripcion: "Salida por entrega N° {$correlativoData['correlativo']}",
                    stock_anterior: $stock_anterior,
                    stock_anterior_base: $stock_anterior_base,
                    cantidad_movimiento: $item['cantidad_lote'],
                    cantidad_movimiento_base: $item['cantidad_base'],
                    nuevo_stock: $nuevo_stock,
                    nuevo_stock_base: $nuevo_stock_base
                );

                // Actualizar Requerimiento Detalle
                $detalle_req = RequerimientosDetalleData::get_detalle_by_id($id_rad);
                $ya_entregado_antes = $detalle_req->cantidad_entregada_base;

                RequerimientosDetalleData::increment_detalle_entregado($id_rad, $item['cantidad_requerimiento'], $item['cantidad_base']);

                // Reload para ver el nuevo estado
                $detalle_req = RequerimientosDetalleData::get_detalle_by_id($id_rad);

                // Actualizar Estado del Item
                $finalizo_item = ($detalle_req->cantidad_entregada_base >= $detalle_req->cantidad_solicitada_base);
                $nuevo_estado_item = $finalizo_item ? EstadoDetalleRequerimiento::Completado->value : EstadoDetalleRequerimiento::EnDespacho->value;

                RequerimientosDetalleData::update_detalle_estado($id_rad, $nuevo_estado_item, $id_empleado_entrega);

                //  Log de Trazabilidad ---
                if ($ya_entregado_antes == 0) { // si es la primera entrega
                    RequerimientosDetalleData::insert_detalle_log($id_rad, $id_empleado_entrega, EstadoDetalleRequerimiento::EnDespacho->getGlosa(), EstadoDetalleRequerimiento::EnDespacho);
                }

                // Por nueva entrega
                RequerimientosDetalleData::insert_detalle_log($id_rad, $id_empleado_entrega, EstadoDetalleRequerimiento::NuevaEntrega->getGlosa((string)$item['cantidad_requerimiento']), EstadoDetalleRequerimiento::NuevaEntrega);

                if ($finalizo_item) { // si ya finalizo
                    RequerimientosDetalleData::insert_detalle_log($id_rad, $id_empleado_entrega, EstadoDetalleRequerimiento::Completado->getGlosa(), EstadoDetalleRequerimiento::Completado);
                }
            }

            return ApiResponse::success(
                $correlativoData['correlativo'],
                "Entrega N° {$correlativoData['correlativo']} registrada exitosamente"
            );
        });
    }

    /**
     * Obtiene los empleados para la entrega
     */
    public function obtener_empleados()
    {
        $data = EmpleadosData::get_empleados();
        return ApiResponse::success($data);
    }
}
