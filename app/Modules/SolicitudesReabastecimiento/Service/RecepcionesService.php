<?php

namespace App\Modules\SolicitudesReabastecimiento\Service;


use App\Data\LotesProductosData;
use App\Services\KardexProductosService;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use App\Modules\SolicitudesReabastecimiento\Data\RecepcionesData;
use App\Modules\SolicitudesReabastecimiento\Data\RecepcionesPrestamoData;
use App\Modules\SolicitudesReabastecimientoAtencion\Data\SolicitudesDetalleData;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudEntregaDetalle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecepcionesService
{
    /**
     * Registrar una recepción de stock para una entrega de LOGÍSTICA.
     */
    public static function registrar_recepcion_logistica(
        int $id_reabastecimiento_entrega,
        int $id_empleado_registro,
        bool $con_incidencia,
        ?string $observacion,
        ?string $fecha_hora_recepcion,
        array $items,
        /**
         * 'id_solicitud_reabastecimiento_detalle''id_entrega_detalle'
         *   'cantidad_base'
         *   'es_nuevo_lote'
         *   'id_lote_existente'
         *   'id_unidad_medida'
         *   'contenido_por_presentacion'
         *   'descripcion'
         *   'fecha_vencimiento'
         *   'fecha_ingreso'
         *   'unidad_abv'
         */
        array $evidencias = []
    ) {
        return DB::transaction(function () use ($id_reabastecimiento_entrega, $id_empleado_registro, $con_incidencia, $observacion, $fecha_hora_recepcion, $items, $evidencias) {

            $fecha_mysql = $fecha_hora_recepcion
                ? Carbon::parse($fecha_hora_recepcion)->toDateTimeString()
                : now()->toDateTimeString();

            // 1. Guardar evidencias
            $evidenciasJson = null;
            if (!empty($evidencias)) {
                $evidenciasData = ArchivoHelper::guardarArchivos('reabastecimiento-recepciones', $evidencias);
                $evidenciasJson = json_encode($evidenciasData);
            }

            // 2. Crear cabecera de recepción logística
            $id_recepcion = RecepcionesData::crear_recepcion(
                $id_reabastecimiento_entrega,
                $id_empleado_registro,
                $fecha_mysql,
                $observacion,
                $evidenciasJson,
                $con_incidencia
            );

            // 3. Pre-cargar lotes existentes en una sola consulta
            $ids_lotes_existentes = collect($items)
                ->where('es_nuevo_lote', false)
                ->map(fn($i) => (int) $i['id_lote_existente'])
                ->filter()
                ->values()
                ->all();

            $lotesMap = !empty($ids_lotes_existentes)
                ? collect(LotesProductosData::get_lote_simple_by_id($ids_lotes_existentes))->keyBy('id_lote')
                : collect();

            // Procesar ítems de la recepción
            $ids_lotes_nuevos = [];
            foreach ($items as $item) {
                $id_solic_detalle = (int) $item['id_solicitud_reabastecimiento_detalle'];
                $cantidad_recep_base = (float) $item['cantidad_base'];
                $es_nuevo_lote = (bool) $item['es_nuevo_lote'];

                // Obtener detalle de la solicitud para saber el almacén destino y producto
                $detalle_sol = SolicitudesDetalleData::get_detalle_by_id($id_solic_detalle);

                $id_almacen_destino = SolicitudesDetalleData::get_almacen_solicitante_id_by_solic_id($detalle_sol->id_solicitud_reabastecimiento);

                // 4. Crear Detalle de Recepción Logística PRIMERO
                $id_entrega_det = (int) $item['id_entrega_detalle'];
                $id_lote_para_detalle = $es_nuevo_lote ? 0 : (int) $item['id_lote_existente'];

                $id_recepcion_detalle = RecepcionesData::crear_detalle_recepcion(
                    id_recepcion: $id_recepcion,
                    id_entrega_detalle: $id_entrega_det,
                    id_lote_producto: $id_lote_para_detalle,
                    es_ajuste_stock: !$es_nuevo_lote,
                    cantidad_recepcionada_base: $cantidad_recep_base
                );

                // 5. Gestión de Lotes (Ajuste vs Nuevo)
                if ($es_nuevo_lote) {
                    $contenido_por_presentacion = (float) ($item['contenido_por_presentacion'] ?? 1);
                    $stock_inicial = $cantidad_recep_base / $contenido_por_presentacion;

                    $correlativoData = LotesProductosData::get_nuevo_correlativo($id_almacen_destino);
                    $id_lote_destino = LotesProductosData::crear_lote(
                        id_producto: (int) $detalle_sol->id_producto,
                        id_unidad_medida: (int) $item['id_unidad_medida'],
                        id_almacen: $id_almacen_destino,
                        id_origen: $id_recepcion_detalle, // AHORA ES EL ID DEL DETALLE DE RECEPCION
                        tabla_origen: 'solicitud_reabastecimiento_recepcion_detalle',
                        correlativo: $correlativoData['correlativo'],
                        numero_correlativo: $correlativoData['numero_correlativo'],
                        stock_inicial: $stock_inicial,
                        contenido_por_presentacion: $contenido_por_presentacion,
                        stock_actual_base: $cantidad_recep_base,
                        fecha_hora_ingreso: isset($item['fecha_ingreso'])
                        ? Carbon::parse($item['fecha_ingreso'])->toDateTimeString()
                        : $fecha_mysql,
                        descripcion: $item['descripcion'] ?? "Ingreso por recepción en reabastecimiento",
                        fecha_vencimiento: isset($item['fecha_vencimiento'])
                        ? Carbon::parse($item['fecha_vencimiento'])->toDateTimeString()
                        : null
                    );

                    $ids_lotes_nuevos[] = $id_lote_destino;

                    // Vincular el nuevo lote al detalle de recepción
                    RecepcionesData::update_detalle_lote($id_recepcion_detalle, $id_lote_destino);

                    $stock_anterior = 0;
                    $stock_anterior_base = 0;
                    $nuevo_stock = $stock_inicial;
                    $nuevo_stock_base = $cantidad_recep_base;
                    $contenido_lot = $contenido_por_presentacion;
                } else {
                    $id_lote_destino = $id_lote_para_detalle;
                    $lote_existente = $lotesMap->get($id_lote_destino);

                    $stock_anterior = $lote_existente['stock_actual'];
                    $stock_anterior_base = $lote_existente['stock_actual_base'];
                    $contenido_lot = $lote_existente['contenido_por_presentacion'];

                    $incremento_lote = $cantidad_recep_base / $contenido_lot;
                    $nuevo_stock = $stock_anterior + $incremento_lote;
                    $nuevo_stock_base = $stock_anterior_base + $cantidad_recep_base;

                    LotesProductosData::update_stock($id_lote_destino, $nuevo_stock, $nuevo_stock_base);
                }

                // 6. Registrar Kardex
                KardexProductosService::registrar_kardex(
                    $id_lote_destino,
                    KardexTipoMovimiento::Ingreso,
                    KardexOrigenMovimiento::Recepcion,
                    "Ingreso por recepción de entrega en reabastecimiento",
                    $cantidad_recep_base / $contenido_lot,
                    $cantidad_recep_base,
                    $nuevo_stock,
                    $nuevo_stock_base,
                    $id_recepcion,
                    $stock_anterior,
                    $stock_anterior_base
                );

                // 7. Actualizar estados de la entrega (Logística)
                self::actualizar_estados_post_recepcion_logistica($id_entrega_det);

                // --- TRAZABILIDAD (REABASTECIMIENTO) ---
                $correlativoEntrega = RecepcionesData::get_correlativo_entrega($id_reabastecimiento_entrega);

                $descripcionLog = "Se recibió una cantidad de " . ($cantidad_recep_base / $contenido_lot) . " " . ($item['unidad_abv'] ?? 'uds') . " desde la entrega logística {$correlativoEntrega}";
                if ($con_incidencia) {
                    $descripcionLog .= ". SE REGISTRÓ CON INCIDENCIA.";
                }

                SolicitudesDetalleData::insert_log_simple($id_solic_detalle, $id_empleado_registro, 'Recepcionado', $descripcionLog);
            }

            $lotes_data = !empty($ids_lotes_nuevos)
                ? LotesProductosData::get_info_to_ticket(ids_lotes: $ids_lotes_nuevos)
                : null;

            return ApiResponse::success($lotes_data, "Recepción logística registrada exitosamente");
        });
    }

    /**
     * Registrar una recepción de stock para una entrega de PRÉSTAMO.
     */
    public static function registrar_recepcion_prestamo(
        int $id_reabastecimiento_entrega,
        int $id_empleado_registro,
        bool $con_incidencia,
        ?string $observacion,
        ?string $fecha_hora_recepcion,
        /**
         *   'id_solicitud_reabastecimiento_detalle'
         *   'id_entrega_detalle'
         *   'cantidad_base'
         *   'es_nuevo_lote'
         *   'id_lote_existente'
         *   'id_unidad_medida'
         *   'contenido_por_presentacion'
         *   'descripcion'
         *   'fecha_vencimiento'
         *   'fecha_ingreso'
         *   'unidad_abv'
         */
        array $items,
        array $evidencias = []
    ) {
        return DB::transaction(function () use ($id_reabastecimiento_entrega, $id_empleado_registro, $con_incidencia, $observacion, $fecha_hora_recepcion, $items, $evidencias) {

            $fecha_mysql = $fecha_hora_recepcion
                ? Carbon::parse($fecha_hora_recepcion)->toDateTimeString()
                : now()->toDateTimeString();

            // 1. Guardar evidencias
            $evidenciasJson = null;
            if (!empty($evidencias)) {
                $evidenciasData = ArchivoHelper::guardarArchivos('reabastecimiento/recepciones', $evidencias);
                $evidenciasJson = json_encode($evidenciasData);
            }

            // 2. Crear cabecera de recepción de préstamo
            $id_recepcion = RecepcionesPrestamoData::crear_recepcion(
                $id_reabastecimiento_entrega,
                $id_empleado_registro,
                $fecha_mysql,
                $observacion,
                $evidenciasJson,
                $con_incidencia
            );

            // 3. Pre-cargar lotes existentes en una sola consulta
            $ids_lotes_existentes = collect($items)
                ->where('es_nuevo_lote', false)
                ->map(fn($i) => (int) $i['id_lote_existente'])
                ->filter()
                ->values()
                ->all();

            $lotesMap = !empty($ids_lotes_existentes)
                ? collect(LotesProductosData::get_lote_simple_by_id($ids_lotes_existentes))->keyBy('id_lote')
                : collect();

            // Procesar ítems de la recepción
            $ids_lotes_nuevos = [];
            foreach ($items as $item) {
                $id_solic_detalle = (int) $item['id_solicitud_reabastecimiento_detalle'];
                $cantidad_recep_base = (float) $item['cantidad_base'];
                $es_nuevo_lote = (bool) $item['es_nuevo_lote'];

                // Obtener detalle de la solicitud para saber el almacén destino y producto
                $detalle_sol = SolicitudesDetalleData::get_detalle_by_id($id_solic_detalle);

                $id_almacen_destino = SolicitudesDetalleData::get_almacen_solicitante_id_by_solic_id($detalle_sol->id_solicitud_reabastecimiento);

                // 4. Crear Detalle de Recepción de Préstamo PRIMERO
                $id_entrega_det = (int) $item['id_entrega_detalle'];
                $id_lote_para_detalle = $es_nuevo_lote ? 0 : (int) $item['id_lote_existente'];

                $id_recepcion_detalle = RecepcionesPrestamoData::crear_detalle_recepcion(
                    id_recepcion: $id_recepcion,
                    id_entrega_detalle: $id_entrega_det,
                    id_lote_producto: $id_lote_para_detalle,
                    es_ajuste_stock: !$es_nuevo_lote,
                    cantidad_recepcionada_base: $cantidad_recep_base
                );

                // 5. Gestión de Lotes (Ajuste vs Nuevo)
                if ($es_nuevo_lote) {
                    $contenido_por_presentacion = (float) ($item['contenido_por_presentacion'] ?? 1);
                    $stock_inicial = $cantidad_recep_base / $contenido_por_presentacion;

                    $correlativoData = LotesProductosData::get_nuevo_correlativo($id_almacen_destino);
                    $id_lote_destino = LotesProductosData::crear_lote(
                        id_producto: (int) $detalle_sol->id_producto,
                        id_unidad_medida: (int) $item['id_unidad_medida'],
                        id_almacen: $id_almacen_destino,
                        id_origen: $id_recepcion_detalle, // AHORA ES EL ID DEL DETALLE DE RECEPCION
                        tabla_origen: 'prestamo_almacen_entrega_recepcion_detalle',
                        correlativo: $correlativoData['correlativo'],
                        numero_correlativo: $correlativoData['numero_correlativo'],
                        stock_inicial: $stock_inicial,
                        contenido_por_presentacion: $contenido_por_presentacion,
                        stock_actual_base: $cantidad_recep_base,
                        fecha_hora_ingreso: isset($item['fecha_ingreso'])
                        ? Carbon::parse($item['fecha_ingreso'])->toDateTimeString()
                        : $fecha_mysql,
                        descripcion: $item['descripcion'] ?? "Ingreso por recepción en reabastecimiento",
                        fecha_vencimiento: isset($item['fecha_vencimiento'])
                        ? Carbon::parse($item['fecha_vencimiento'])->toDateTimeString()
                        : null
                    );

                    $ids_lotes_nuevos[] = $id_lote_destino;

                    // Vincular el nuevo lote al detalle de recepción
                    RecepcionesPrestamoData::update_detalle_lote($id_recepcion_detalle, $id_lote_destino);

                    // Calcular valores directamente sin re-consultar el lote recién creado
                    $stock_anterior = 0;
                    $stock_anterior_base = 0;
                    $nuevo_stock = $stock_inicial;
                    $nuevo_stock_base = $cantidad_recep_base;
                    $contenido_lot = $contenido_por_presentacion;
                } else {
                    $id_lote_destino = $id_lote_para_detalle;
                    $lote_existente = $lotesMap->get($id_lote_destino);

                    $stock_anterior = $lote_existente['stock_actual'];
                    $stock_anterior_base = $lote_existente['stock_actual_base'];
                    $contenido_lot = $lote_existente['contenido_por_presentacion'];

                    $incremento_lote = $cantidad_recep_base / $contenido_lot;
                    $nuevo_stock = $stock_anterior + $incremento_lote;
                    $nuevo_stock_base = $stock_anterior_base + $cantidad_recep_base;

                    LotesProductosData::update_stock($id_lote_destino, $nuevo_stock, $nuevo_stock_base);
                }

                // 6. Registrar Kardex
                KardexProductosService::registrar_kardex(
                    $id_lote_destino,
                    KardexTipoMovimiento::Ingreso,
                    KardexOrigenMovimiento::Recepcion,
                    "Ingreso por recepción de entrega en reabastecimiento",
                    $cantidad_recep_base / $contenido_lot,
                    $cantidad_recep_base,
                    $nuevo_stock,
                    $nuevo_stock_base,
                    $id_recepcion,
                    $stock_anterior,
                    $stock_anterior_base
                );

                // 7. Actualizar estados de la entrega (Préstamo)
                self::actualizar_estados_post_recepcion_prestamo($id_entrega_det);

                // --- TRAZABILIDAD (REABASTECIMIENTO) ---
                $correlativoEntrega = RecepcionesPrestamoData::get_correlativo_entrega($id_reabastecimiento_entrega);

                $descripcionLog = "Se recibió una cantidad de " . ($cantidad_recep_base / $contenido_lot) . " " . ($item['unidad_abv'] ?? 'uds') . " desde la entrega de préstamo {$correlativoEntrega}";
                if ($con_incidencia) {
                    $descripcionLog .= ". SE REGISTRÓ CON INCIDENCIA.";
                }

                SolicitudesDetalleData::insert_log_simple($id_solic_detalle, $id_empleado_registro, 'Recepcionado', $descripcionLog);
            }

            $lotes_data = !empty($ids_lotes_nuevos)
                ? LotesProductosData::get_info_to_ticket(ids_lotes: $ids_lotes_nuevos)
                : null;

            return ApiResponse::success($lotes_data, "Recepción de préstamo registrada exitosamente");
        });
    }

    /**
     * Obtener el historial de recepciones de una entrega
     */
    public static function obtener_historial_recepciones(int $id_entrega, string $tipo_entrega)
    {
        if ($tipo_entrega === 'Solicitud') {
            $cabeceras = RecepcionesData::get_historial_recepciones($id_entrega);
            foreach ($cabeceras as $cab) {
                $cab->evidencias = $cab->evidencias ? json_decode($cab->evidencias) : null;
                $cab->detalles = RecepcionesData::get_detalles_recepcion($cab->id_recepcion);
            }
        } else {
            $cabeceras = RecepcionesPrestamoData::get_historial_recepciones($id_entrega);
            foreach ($cabeceras as $cab) {
                $cab->evidencias = $cab->evidencias ? json_decode($cab->evidencias) : null;
                $cab->detalles = RecepcionesPrestamoData::get_detalles_recepcion($cab->id_recepcion);
            }
        }

        return ApiResponse::success($cabeceras);
    }

    /**
     * Lógica de negocio para actualizar estados después de una recepción LOGÍSTICA
     */
    private static function actualizar_estados_post_recepcion_logistica(int $id_entrega_detalle)
    {
        $detalle = RecepcionesData::get_entrega_detalle_by_id($id_entrega_detalle);
        if (!$detalle)
            return;

        $total_recibido = RecepcionesData::get_cantidad_recepcionada_total_base_detalle($id_entrega_detalle);

        // Determinar estado del detalle
        $nuevo_estado_det = ($total_recibido >= $detalle->cantidad_base - 0.0001) ? EstadoSolicitudEntregaDetalle::RecepcionCompleta : EstadoSolicitudEntregaDetalle::RecepcionadoParcialmente;
        RecepcionesData::update_entrega_detalle_estado($id_entrega_detalle, $nuevo_estado_det->value);

        // Determinar estado de la cabecera
        $id_entrega = (int) $detalle->id_reabastecimiento_entrega;
        $todos_detalles = RecepcionesData::get_entrega_detalles($id_entrega);

        $todos_recibidos = $todos_detalles->every(fn($d) => ($d->estado ?? $d->state) === EstadoSolicitudEntregaDetalle::RecepcionCompleta->value);
        $algun_recibido = $todos_detalles->contains(fn($d) => in_array($d->estado ?? $d->state, [EstadoSolicitudEntregaDetalle::RecepcionCompleta->value, EstadoSolicitudEntregaDetalle::RecepcionadoParcialmente->value]));

        $nuevo_estado_cab = $todos_recibidos ? EstadoSolicitudEntregaDetalle::RecepcionCompleta : ($algun_recibido ? EstadoSolicitudEntregaDetalle::RecepcionadoParcialmente : EstadoSolicitudEntregaDetalle::EnDespacho);
        RecepcionesData::update_entrega_estado($id_entrega, $nuevo_estado_cab->value);
    }

    /**
     * Lógica de negocio para actualizar estados después de una recepción de PRÉSTAMO
     */
    private static function actualizar_estados_post_recepcion_prestamo(int $id_entrega_detalle)
    {
        $detalle = RecepcionesPrestamoData::get_entrega_detalle_by_id($id_entrega_detalle);
        if (!$detalle)
            return;

        $total_recibido = RecepcionesPrestamoData::get_cantidad_recepcionada_total_base_detalle($id_entrega_detalle);

        // Determinar estado del detalle
        $nuevo_estado_det = ($total_recibido >= $detalle->cantidad_base - 0.0001) ? EstadoSolicitudEntregaDetalle::RecepcionCompleta : EstadoSolicitudEntregaDetalle::RecepcionadoParcialmente;
        RecepcionesPrestamoData::update_entrega_detalle_estado($id_entrega_detalle, $nuevo_estado_det->value);

        // Determinar estado de la cabecera
        $id_entrega = (int) $detalle->id_prestamo_almacen_entrega;
        $todos_detalles = RecepcionesPrestamoData::get_entrega_detalles($id_entrega);

        $todos_recibidos = $todos_detalles->every(fn($d) => ($d->estado ?? $d->state) === EstadoSolicitudEntregaDetalle::RecepcionCompleta->value);
        $algun_recibido = $todos_detalles->contains(fn($d) => in_array($d->estado ?? $d->state, [EstadoSolicitudEntregaDetalle::RecepcionCompleta->value, EstadoSolicitudEntregaDetalle::RecepcionadoParcialmente->value]));

        $nuevo_estado_cab = $todos_recibidos ? EstadoSolicitudEntregaDetalle::RecepcionCompleta : ($algun_recibido ? EstadoSolicitudEntregaDetalle::RecepcionadoParcialmente : EstadoSolicitudEntregaDetalle::EnDespacho);
        RecepcionesPrestamoData::update_entrega_estado($id_entrega, $nuevo_estado_cab->value);
    }
}
