<?php

namespace App\Modules\SolicitudesReabastecimientoAtencion\Service;

use App\Data\KardexProductosData;
use App\Data\LotesProductosData;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalle;
use App\Shared\Responses\ApiResponse;
use App\Modules\SolicitudesReabastecimientoAtencion\Data\EntregasData;
use App\Modules\SolicitudesReabastecimientoAtencion\Data\EntregasDetalleData;
use App\Modules\SolicitudesReabastecimientoAtencion\Data\SolicitudesDetalleData;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalleLog;
use Illuminate\Support\Facades\DB;
use App\Shared\Helpers\ArchivoHelper;

class EntregaService
{
    /**
     * Obtener el historial de entregas y sus detalles de una solicitud 
     * hechas por logistica o por un prestamo
     */
    public static function obtener_historial_entregas(int $id_solicitud)
    {
        $data_logistica = EntregasData::get_historial_entregas_logistica(id_solicitud: $id_solicitud);

        foreach ($data_logistica as $entrega) {
            $entrega->evidencias = $entrega->evidencias ? json_decode($entrega->evidencias) : null;
            $entrega->detalles = EntregasDetalleData::get_detalles_entrega_logistica((int) $entrega->id_reabastecimiento_entrega);
        }

        $data_prestamo = EntregasData::get_historial_entregas_prestamo($id_solicitud);
        foreach ($data_prestamo as $entrega) {
            $entrega->evidencias = $entrega->evidencias ? json_decode($entrega->evidencias) : null;
            $entrega->detalles = EntregasDetalleData::get_detalles_entrega_prestamo((int) $entrega->id_prestamo_entrega);
        }

        return ApiResponse::success([
            'logistica' => $data_logistica,
            'prestamo' => $data_prestamo
        ]);
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
        ?array $evidencias, // archivos
        array $detalles // {id_solicitud_detalle, id_lote_producto, cantidad_base, cantidad_lote, cantidad_solicitud
    ) {
        return DB::transaction(function () use ($id_almacen_entrega, $id_empleado_entrega, $id_solicitud, $id_empleado_recibe, $fecha_hora_entrega, $observacion, $evidencias, $detalles) {

            // Procesar Evidencias si existen
            $evidenciasData = null;
            if (!empty($evidencias)) {
                $evidenciasData = ArchivoHelper::guardarArchivos('reabastecimiento_entregas', $evidencias);
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
                $evidenciasData,
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
                    $id_lote,
                    KardexTipoMovimiento::Salida,
                    KardexOrigenMovimiento::Entrega,
                    "Salida por entrega N° {$correlativoData['correlativo']} debido a una solicitud de reabastecimiento",
                    $item['cantidad_lote'],
                    $item['cantidad_base'],
                    $nuevo_stock,
                    $nuevo_stock_base,
                    $id_detalle_entrega,
                    $stock_anterior,
                    $stock_anterior_base,
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
                    SolicitudesDetalleData::insert_detalle_log(
                        $id_detalle_sol,
                        $id_empleado_entrega,
                        EstadoSolicitudDetalleLog::EnDespacho->getGlosa(),
                        EstadoSolicitudDetalleLog::EnDespacho
                    );
                }

                // Por nueva entrega
                SolicitudesDetalleData::insert_detalle_log(
                    $id_detalle_sol,
                    $id_empleado_entrega,
                    EstadoSolicitudDetalleLog::NuevaEntrega->getGlosa((string)$item['cantidad_solicitud']),
                    EstadoSolicitudDetalleLog::NuevaEntrega
                );

                if ($finalizo_item) { // si ya finalizo
                    SolicitudesDetalleData::insert_detalle_log(
                        $id_detalle_sol,
                        $id_empleado_entrega,
                        EstadoSolicitudDetalleLog::Completado->getGlosa(),
                        EstadoSolicitudDetalleLog::Completado
                    );
                }
            }

            return ApiResponse::success(
                $correlativoData['correlativo'],
                "Entrega N° {$correlativoData['correlativo']} registrada exitosamente"
            );
        });
    }
}
