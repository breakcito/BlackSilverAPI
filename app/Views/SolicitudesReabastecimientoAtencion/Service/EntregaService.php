<?php

namespace App\Views\SolicitudesReabastecimientoAtencion\Service;

use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalle;
use App\Shared\Responses\ApiResponse;
use App\Views\SolicitudesReabastecimientoAtencion\Data\AuxData;
use App\Views\SolicitudesReabastecimientoAtencion\Data\EntregasData;
use App\Views\SolicitudesReabastecimientoAtencion\Data\EntregasDetalleData;
use App\Views\SolicitudesReabastecimientoAtencion\Data\SolicitudesDetalleData;
use Illuminate\Support\Facades\DB;

class EntregaService
{

    /**
     * Obtiene el historial de entregas de una solicitud y sus detalles.
     */
    public static function obtener_historial_entregas(int $id_solicitud)
    {
        $data = EntregasData::get_historial_entregas(id_solicitud: $id_solicitud);

        foreach ($data as $entrega) {
            $entrega->detalles = EntregasDetalleData::get_detalles_entrega(id_entrega: (int) $entrega->id_requerimiento_almacen_entrega);
        }

        return ApiResponse::success($data);
    }

    /**
     * Registra una entrega física de materiales.
     */
    public static function registrar_entrega(
        int $id_almacen_entrega,
        int $id_empleado_entrega,
        int $id_solicitud,
        int $id_empleado_recibe,
        string $fecha_hora_entrega,
        ?string $observacion,
        array $detalles // {id_solicitud_detalle, id_lote_producto, cantidad_base, cantidad_lote, cantidad_solicitud
    ) {
        return DB::transaction(function () use ($id_almacen_entrega, $id_empleado_entrega, $id_solicitud, $id_empleado_recibe, $fecha_hora_entrega, $observacion, $detalles) {

            // Validar Stock
            foreach ($detalles as $item) {
                $lote = AuxData::get_lote_by_id($item['id_lote_producto']);
                if (!$lote || $lote->stock_actual_base < $item['cantidad_base']) {
                    return ApiResponse::error("Stock insuficiente en el lote: " . $lote->correlativo);
                }
            }

            // Generar Correlativo
            $correlativoData = EntregasData::get_nuevo_correlativo($id_almacen_entrega);

            // Crear Cabecera de Entrega
            $id_entrega = EntregasData::crear_entrega(
                $id_solicitud,
                $id_almacen_entrega,
                $id_empleado_entrega,
                $id_empleado_recibe,
                $correlativoData['correlativo'],
                $correlativoData['numero_correlativo'],
                $fecha_hora_entrega,
                $observacion,
            );

            foreach ($detalles as $item) {
                $id_detalle_sol = $item['id_solicitud_detalle'];
                $id_lote = $item['id_lote_producto'];

                // Crear Detalle de Entrega
                $id_detalle_entrega = EntregasDetalleData::crear_detalle_entrega(
                    $id_entrega,
                    $id_detalle_sol,
                    $id_lote,
                    $item['cantidad_base'],
                    $item['cantidad_lote'],
                    $item['cantidad_solicitud']
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

                // Actualizar el Detalle de la solicitud
                $detalle_sol = SolicitudesDetalleData::get_detalle_by_id($id_detalle_sol);
                $ya_entregado_antes = $detalle_sol->cantidad_entregada_base;

                SolicitudesDetalleData::increment_detalle_entregado($id_detalle_sol, $item['cantidad_solicitud'], $item['cantidad_base']);

                // Reload para ver el nuevo estado
                $detalle_sol = SolicitudesDetalleData::get_detalle_by_id($id_detalle_sol);

                // Actualizar Estado del Item
                $finalizo_item = ($detalle_sol->cantidad_entregada_base >= $detalle_sol->cantidad_solicitada_base);
                $nuevo_estado_item = $finalizo_item ? EstadoSolicitudDetalle::Completado->value : EstadoSolicitudDetalle::EnDespacho->value;

                SolicitudesDetalleData::update_detalle_estado($id_detalle_sol, $nuevo_estado_item, $id_empleado_entrega);

                //  Log de Trazabilidad ---
                if ($ya_entregado_antes == 0) { // si es la primera entrega
                    SolicitudesDetalleData::insert_detalle_log($id_detalle_sol, $id_empleado_entrega, EstadoSolicitudDetalle::EnDespacho->getGlosa(), EstadoSolicitudDetalle::EnDespacho->value);
                }

                // Por nueva entrega
                SolicitudesDetalleData::insert_detalle_log($id_detalle_sol, $id_empleado_entrega, EstadoSolicitudDetalle::NuevaEntrega->getGlosa((string)$item['cantidad_solicitud']), EstadoSolicitudDetalle::NuevaEntrega->value);

                if ($finalizo_item) { // si ya finalizo
                    SolicitudesDetalleData::insert_detalle_log($id_detalle_sol, $id_empleado_entrega, EstadoSolicitudDetalle::Completado->getGlosa(), EstadoSolicitudDetalle::Completado->value);
                }
            }

            return ApiResponse::success(
                $correlativoData['correlativo'],
                "Entrega N° {$correlativoData['correlativo']} registrada exitosamente"
            );
        });
    }
}
