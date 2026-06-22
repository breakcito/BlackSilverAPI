<?php

namespace App\Modules\SolicitudesReabastecimientoAtencion\Service;


use App\Data\LotesProductosData;
use App\Services\ActivosFijosService;
use App\Services\LotesProductosService;
use App\Shared\Enums\ActivoFijo\MovimientoActivoFijo;
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
        array $detalles // {id_solicitud_detalle, id_lote_producto, id_activo_fijo, cantidad_base, cantidad_lote, cantidad_solicitud
    ) {
        return DB::transaction(function () use ($id_almacen_entrega, $id_empleado_entrega, $id_solicitud, $id_empleado_recibe, $fecha_hora_entrega, $observacion, $evidencias, $detalles) {

            // Procesar Evidencias si existen
            $evidenciasData = null;
            if (!empty($evidencias)) {
                $evidenciasData = ArchivoHelper::guardarArchivos('reabastecimiento_entregas', $evidencias);
            }

            // Pre-cargar todos los lotes (solo ítems sin activo fijo)
            $items_con_lote = array_filter($detalles, fn($i) => empty($i['id_activo_fijo']));
            $ids_lotes = array_map(fn($i) => (int) $i['id_lote_producto'], $items_con_lote);

            $lotesMap = !empty($ids_lotes)
                ? collect(LotesProductosData::get_lote_dinamico_by_id(id_lote: $ids_lotes, columnas: ['stock_actual_base', 'correlativo']))
                    ->keyBy('id_lote')
                : collect();

            // Validar Stock solo para productos comunes
            foreach ($items_con_lote as $item) {
                $lote = $lotesMap->get((int) $item['id_lote_producto']);
                if (!$lote || $lote['stock_actual_base'] < $item['cantidad_base']) {
                    return ApiResponse::error("Stock insuficiente en el lote: " . ($lote['correlativo']));
                }
            }

            // Generar Correlativo
            $correlativoData = EntregasData::get_nuevo_correlativo();

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
                $id_activo      = !empty($item['id_activo_fijo']) ? (int) $item['id_activo_fijo'] : null;
                $es_activo      = $id_activo !== null;

                if ($es_activo) {
                    // --- Camino: Activo Fijo ---
                    // El activo deja de estar en el almacen/mina donde se encontraba
                    ActivosFijosService::new_ubicacion(
                        id_activo: $id_activo,
                        tipo_movimiento: MovimientoActivoFijo::DeAlmacenAAlmacen,
                        id_almacen: null,
                        id_mina: null,
                        descripcion: "Entrega por solicitud de reabastecimiento N° {$correlativoData['correlativo']}",
                        fecha_hora_movimiento: $fecha_hora_entrega
                    );

                    // Detalle sin lote (activos no tienen lote)
                    EntregasDetalleData::crear_detalle_entrega(
                        $id_entrega,
                        $id_detalle_sol,
                        null,   // id_lote
                        1,      // cantidad_base
                        1,      // cantidad_lote
                        1,      // cantidad_solicitud
                        $id_activo
                    );
                } else {
                    // --- Camino: Producto Común con Lote ---
                    $id_lote = $item['id_lote_producto'];

                    $id_detalle_entrega = EntregasDetalleData::crear_detalle_entrega(
                        $id_entrega,
                        $id_detalle_sol,
                        $id_lote,
                        $item['cantidad_base'],
                        $item['cantidad_lote'],
                        $item['cantidad_solicitud']
                    );

                    LotesProductosService::update_stock(
                        id_lote: $id_lote,
                        id_origen: $id_detalle_entrega,
                        tabla_origen: null,
                        tipo_origen: KardexOrigenMovimiento::Entrega,
                        tipo_movimiento: KardexTipoMovimiento::Salida,
                        cantidad_movimiento_base: $item['cantidad_base'],
                        descripcion: "Salida por entrega N° {$correlativoData['correlativo']} debido a una solicitud de reabastecimiento",
                    );
                }

                // Actualizar el Detalle de la solicitud (común)
                $detalle_sol = SolicitudesDetalleData::get_detalle_by_id($id_detalle_sol);
                $ya_entregado_antes = $detalle_sol->cantidad_entregada_base;

                $cant_solicitud = $es_activo ? 1 : $item['cantidad_solicitud'];
                $cant_base      = $es_activo ? 1 : $item['cantidad_base'];

                SolicitudesDetalleData::increment_detalle_entregado($id_detalle_sol, $cant_solicitud, $cant_base);

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
                    EstadoSolicitudDetalleLog::NuevaEntrega->getGlosa((string) $cant_solicitud),
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
