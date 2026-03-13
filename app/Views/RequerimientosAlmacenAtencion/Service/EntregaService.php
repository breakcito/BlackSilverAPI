<?php

namespace App\Views\RequerimientosAlmacenAtencion\Service;

use App\Shared\Enums\RequerimientoAlmacen\EstadoDetalleRequerimiento;
use App\Shared\Responses\ApiResponse;
use App\Views\RequerimientosAlmacenAtencion\Data\AuxData;
use App\Views\RequerimientosAlmacenAtencion\Data\EntregasData;
use App\Views\RequerimientosAlmacenAtencion\Data\EntregasDetalleData;
use App\Views\RequerimientosAlmacenAtencion\Data\RequerimientosData;
use App\Views\RequerimientosAlmacenAtencion\Data\RequerimientosDetalleData;
use Illuminate\Support\Facades\DB;

class EntregaService
{
    /**
     * Obtiene los lotes disponibles para una lista de productos de un almacén.
     */
    public function obtener_lotes_disponibles(array $ids_productos, int $id_almacen)
    {
        $data = AuxData::get_lotes_disponibles($ids_productos, $id_almacen);
        return ApiResponse::success($data);
    }

    /**
     * Obtiene el historial de entregas y sus detalles.
     */
    public function obtener_historial_entregas(int $id_requerimiento)
    {
        $data = EntregasData::get_historial_entregas(id_requerimiento: $id_requerimiento);

        foreach ($data as $entrega) {
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
        array $detalles // {id_lote_producto, cantidad_base, cantidad_lote, cantidad_requerimiento}
    ) {
        return DB::transaction(function () use ($id_empleado_entrega, $id_requerimiento, $id_empleado_recibe, $fecha_entrega, $observacion, $detalles) {

            // Validar Stock
            foreach ($detalles as $item) {
                $lote = AuxData::get_lote_by_id($item['id_lote_producto']);
                if (!$lote || $lote->stock_actual_base < $item['cantidad_base']) {
                    return ApiResponse::error("Stock insuficiente en el lote: " . $lote->correlativo);
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
                strtotime($fecha_entrega), // Asumiendo que espera timestamp segun EntregasData anterior, pero revisemos
                $observacion,
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

                // Cargar Lote para Kardex
                $lote = AuxData::get_lote_by_id($id_lote);
                $stock_anterior = $lote->stock_actual;
                $stock_anterior_base = $lote->stock_actual_base;
                $nuevo_stock = $stock_anterior - $item['cantidad_lote'];
                $nuevo_stock_base = $stock_anterior_base - $item['cantidad_base'];

                // Actualizar Stock del Lote
                AuxData::update_lote_stock($id_lote, $nuevo_stock, $nuevo_stock_base);

                // Registrar Kardex (Salida)
                AuxData::registrar_kardex(
                    $id_lote,
                    $id_detalle_entrega,
                    $stock_anterior,
                    $stock_anterior_base,
                    $item['cantidad_lote'],
                    $item['cantidad_base'],
                    $nuevo_stock,
                    $nuevo_stock_base,
                    "Salida por entrega N° {$correlativoData['correlativo']}"
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
                    RequerimientosDetalleData::insert_detalle_log($id_rad, $id_empleado_entrega, EstadoDetalleRequerimiento::EnDespacho->getGlosa(), EstadoDetalleRequerimiento::EnDespacho->value);
                }

                // Por nueva entrega
                RequerimientosDetalleData::insert_detalle_log($id_rad, $id_empleado_entrega, EstadoDetalleRequerimiento::NuevaEntrega->getGlosa((string)$item['cantidad_requerimiento']), EstadoDetalleRequerimiento::NuevaEntrega->value);

                if ($finalizo_item) { // si ya finalizo
                    RequerimientosDetalleData::insert_detalle_log($id_rad, $id_empleado_entrega, EstadoDetalleRequerimiento::Completado->getGlosa(), EstadoDetalleRequerimiento::Completado->value);
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
        $data = AuxData::get_empleados();
        return ApiResponse::success($data);
    }
}
