<?php

namespace App\Modules\OrdenesCompra\Service;

use App\Data\LotesProductosData;
use App\Modules\OrdenesCompra\Data\OrdenCompraData;
use App\Modules\OrdenesCompra\Data\RecepcionesOCData;
use App\Services\ActivosFijosService;
use App\Services\LotesProductosService;
use App\Shared\Enums\ActivoFijo\EstadoActivoFijo;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use App\Shared\Enums\OrdenCompra\EstadoOrdenCompra;
use App\Shared\Enums\OrdenCompra\EstadoOrdenCompraDetalle;
use App\Shared\Enums\OrdenCompra\EstadoOrdenCompraDetalleLog;
use App\Shared\Enums\OrdenCompra\EstadoOrdenCompraRecepcion;
use App\Shared\Enums\OrdenCompra\EstadoOrdenCompraRecepcionDetalle;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Modules\OrdenesCompra\Service\OCComprobanteService;
use App\Shared\Enums\_Generic\Moneda;
use App\Shared\Enums\_Generic\TipoComprobante;

class RecepcionesOCService
{
    /**
     * Registrar una recepción de stock para una Orden de Compra.
     * 
     * @param array $items Lista de objetos con el detalle de recepción.
     *   Para productos comunes:
     *   {
     *     id_orden_compra_detalle: int,
     *     cantidad_base: float,
     *     es_nuevo_lote: bool,
     *     id_lote_existente: int|null,
     *     id_unidad_medida: int,
     *     contenido_por_presentacion: float,
     *     descripcion: string|null,
     *     fecha_vencimiento: string|null,
     *     fecha_ingreso: string|null,
     *     unidad_abv: string
     *   }
     *   Para activos fijos (tipo_bien = 'ActivoFijo'):
     *   {
     *     id_orden_compra_detalle: int,
     *     es_activo_fijo: true,
     *     descripcion_activo: string|null,   // descripción adicional del activo
     *     id_almacen_destino: int|null,      // si se recepciona en almacén
     *     id_mina_destino: int|null          // si se recepciona directo en mina
     *   }
     * @param array $evidencias Lista de archivos físicos (UploadedFile) de evidencias de la recepción.
     * @param array $evidencias_comprobante Lista de archivos físicos para el comprobante (si se adjunta).
     */
    public static function registrar_recepcion_oc(
        int $id_orden_compra,
        int $id_almacen_recepcionista,
        int $id_empleado_registro,
        bool $con_incidencia,
        ?string $observacion,
        ?string $fecha_hora_recepcion,
        ?string $serie_guia,
        ?string $numero_guia,
        array $items,
        array $evidencias = [],
        //
        // Datos para crear comprobante
        //
        ?TipoComprobante $tipo_comprobante = null,
        ?string $serie_comprobante = null,
        ?string $numero_comprobante = null,
        ?string $fecha_emision_comprobante = null,
        ?string $observacion_comprobante = null,
        array $evidencias_comprobante = [],
        ?Moneda $moneda_comprobante = null,
        float $tipo_cambio_comprobante = 1,
        bool $es_auditable_comprobante = false,
        float $total_antes_igv_comprobante = 0,
        float $total_antes_igv_soles_comprobante = 0,
        bool $incluye_igv_comprobante = true,
        float $porcentaje_igv_comprobante = 18,
        float $monto_igv_comprobante = 0,
        float $monto_igv_soles_comprobante = 0,
        float $total_despues_igv_comprobante = 0,
        float $total_despues_igv_soles_comprobante = 0
    ) {
        try {
            return DB::transaction(function () use (
                $id_orden_compra,
                $id_almacen_recepcionista,
                $id_empleado_registro,
                $con_incidencia,
                $observacion,
                $fecha_hora_recepcion,
                $serie_guia,
                $numero_guia,
                $items,
                $evidencias,
                $tipo_comprobante,
                $serie_comprobante,
                $numero_comprobante,
                $fecha_emision_comprobante,
                $observacion_comprobante,
                $evidencias_comprobante,
                $moneda_comprobante,
                $tipo_cambio_comprobante,
                $es_auditable_comprobante,
                $total_antes_igv_comprobante,
                $total_antes_igv_soles_comprobante,
                $incluye_igv_comprobante,
                $porcentaje_igv_comprobante,
                $monto_igv_comprobante,
                $monto_igv_soles_comprobante,
                $total_despues_igv_comprobante,
                $total_despues_igv_soles_comprobante
            ) {
                // Validar que no se mezclen activos fijos y productos comunes
                $tiene_activos = false;
                $tiene_comunes = false;
                foreach ($items as $item) {
                    if (!empty($item['es_activo_fijo'])) {
                        $tiene_activos = true;
                    } else {
                        $tiene_comunes = true;
                    }
                }

                if ($tiene_activos && $tiene_comunes) {
                    throw new \Exception("No se pueden recepcionar activos fijos y productos de consumo común en la misma recepción.");
                }

            $fecha_mysql = $fecha_hora_recepcion
                ? Carbon::parse($fecha_hora_recepcion)->toDateTimeString()
                : now()->toDateTimeString();

            // 1. Guardar evidencias
            $evidenciasJson = null;
            if (!empty($evidencias)) {
                $evidenciasData = ArchivoHelper::guardarArchivos('ordenes-compra-recepciones', $evidencias);
                $evidenciasJson = json_encode($evidenciasData);
            }

            // 2. Calcular correlativo
            $numero_correlativo = RecepcionesOCData::get_proximo_numero_correlativo($id_orden_compra);

            // 3. Crear cabecera de recepción
            $id_recepcion = RecepcionesOCData::crear_recepcion(
                id_orden_compra: $id_orden_compra,
                id_almacen: $id_almacen_recepcionista,
                id_empleado: $id_empleado_registro,
                numero_correlativo: $numero_correlativo,
                fecha_hora_recepcion: $fecha_mysql,
                observacion: $observacion,
                evidencias: $evidenciasJson,
                con_incidencia: $con_incidencia,
                serie_guia: $serie_guia,
                numero_guia: $numero_guia,
                estado: EstadoOrdenCompraRecepcion::RecepcionCompleta // Se asume completa la recepción del evento
            );

            // 4. Pre-cargar lotes existentes
            $ids_lotes_existentes = collect($items)
                ->where('es_nuevo_lote', false)
                ->map(fn($i) => (int) $i['id_lote_existente'])
                ->filter()
                ->values()
                ->all();

            $yaRecibidoAntesMap = [];
            foreach ($items as $i) {
                $id_oc_det = (int) $i['id_orden_compra_detalle'];
                if (!isset($yaRecibidoAntesMap[$id_oc_det])) {
                    $yaRecibidoAntesMap[$id_oc_det] = (float) RecepcionesOCData::get_cantidad_recepcionada_total_base_detalle($id_oc_det);
                }
            }

            $totalesBaseRequest = [];
            foreach ($items as $i) {
                $id_oc_det = (int) $i['id_orden_compra_detalle'];
                $es_activo = !empty($i['es_activo_fijo']);
                $cant_base = $es_activo ? 1 : (float) $i['cantidad_base'];
                if (!isset($totalesBaseRequest[$id_oc_det])) {
                    $totalesBaseRequest[$id_oc_det] = 0;
                }
                $totalesBaseRequest[$id_oc_det] += $cant_base;
            }

            // Procesar cada item (lote o activo)
            $detallesAgrupados = [];
            $ids_lotes_nuevos = [];
            foreach ($items as $item) {
                $id_oc_detalle = (int) $item['id_orden_compra_detalle'];
                $es_activo_fijo = !empty($item['es_activo_fijo']);

                $oc_detalle = RecepcionesOCData::get_oc_detalle_by_id($id_oc_detalle);
                if (!$oc_detalle)
                    continue;

                // --- Camino: Activo Fijo ---
                if ($es_activo_fijo) {
                    $id_almacen_activo = !empty($item['id_almacen_destino']) ? (int) $item['id_almacen_destino'] : null;
                    $id_mina_activo = !empty($item['id_mina_destino']) ? (int) $item['id_mina_destino'] : null;

                    $estado_activo = !empty($id_almacen_activo) ? EstadoActivoFijo::EnAlmacen : EstadoActivoFijo::EnUso;

                    // 1. Crear detalle de recepcion primero, vinculando el activo temporalmente como nulo
                    $id_recepcion_detalle = RecepcionesOCData::crear_detalle_recepcion(
                        id_recepcion: $id_recepcion,
                        id_oc_detalle: $id_oc_detalle,
                        id_lote_producto: 0, // no aplica
                        es_ajuste_stock: false,
                        cantidad_recepcionada: 1,
                        cantidad_recepcionada_base: 1,
                        comentario: $item['descripcion_activo'] ?? null,
                        estado: EstadoOrdenCompraRecepcionDetalle::RecepcionCompleta,
                        id_activo_fijo: null // se asocia después
                    );

                    // 2. Crear el activo fijo recién comprado pasándole el id_orden_compra_recepcion_detalle
                    // crear_activo() internamente registra NuevoActivo en el log de ubicación.
                    $resultado = ActivosFijosService::crear_activo(
                        id_producto: (int) $oc_detalle->id_producto,
                        id_almacen: $id_almacen_activo,
                        id_mina: $id_mina_activo,
                        id_marca: !empty($item['id_marca']) ? (int) $item['id_marca'] : null,
                        codigo: !empty($item['codigo']) ? $item['codigo'] : null,
                        numero_serie: !empty($item['numero_serie']) ? $item['numero_serie'] : null,
                        modelo: !empty($item['modelo']) ? $item['modelo'] : null,
                        yearcito_modelo: !empty($item['yearcito_modelo']) ? (int) $item['yearcito_modelo'] : null,
                        descripcion: $item['descripcion_activo'] ?? "Ingreso por recepcion de OC",
                        especificaciones: !empty($item['especificaciones']) ? $item['especificaciones'] : null,
                        fecha_hora_ingreso: $fecha_mysql,
                        estado: $estado_activo,
                        return_objecto: false,
                        id_empleado_responsable: !empty($item['id_empleado_responsable']) ? (int) $item['id_empleado_responsable'] : null,
                        costo_compra: (float) $oc_detalle->precio_unitario,
                        id_orden_compra_detalle: $id_oc_detalle,
                        serie_factura_compra: $serie_comprobante,
                        numero_factura_compra: $numero_comprobante,
                        id_orden_compra_recepcion_detalle: $id_recepcion_detalle
                    );

                    if (!$resultado['success']) {
                        throw new \Exception($resultado['message'] ?? "Error al registrar el activo fijo.");
                    }
                    $id_activo_creado = $resultado['data'];

                    // 3. Vincular el activo creado al detalle de recepción
                    RecepcionesOCData::update_detalle_activo($id_recepcion_detalle, $id_activo_creado);

                    // Acumular para post-procesamiento
                    if (!isset($detallesAgrupados[$id_oc_detalle])) {
                        $total_ya_recibido_antes = $yaRecibidoAntesMap[$id_oc_detalle] ?? RecepcionesOCData::get_cantidad_recepcionada_total_base_detalle($id_oc_detalle);
                        $detallesAgrupados[$id_oc_detalle] = [
                            'cantidad_recepcionada' => 1,
                            'cantidad_recepcionada_base' => 1,
                            'total_ya_recibido_antes' => $total_ya_recibido_antes,
                        ];
                    } else {
                        $detallesAgrupados[$id_oc_detalle]['cantidad_recepcionada'] += 1;
                        $detallesAgrupados[$id_oc_detalle]['cantidad_recepcionada_base'] += 1;
                    }

                    continue; // Saltar el flujo de lotes
                }

                // --- Camino: Producto Común con Lote ---
                $cantidad_recep_base = (float) $item['cantidad_base'];
                $es_nuevo_lote = (bool) $item['es_nuevo_lote'];

                // 1. Calcular estado previsto del detalle
                $total_ya_recibido_antes = $yaRecibidoAntesMap[$id_oc_detalle];
                $total_final_previsto = $total_ya_recibido_antes + $totalesBaseRequest[$id_oc_detalle];

                $estado_det_recep = ($total_final_previsto >= $oc_detalle->cantidad_requerida_base - 0.001)
                    ? EstadoOrdenCompraRecepcionDetalle::RecepcionCompleta
                    : EstadoOrdenCompraRecepcionDetalle::RecepcionadoParcialmente;

                // 2. Crear Detalle de Recepción PRIMERO
                $id_lote_para_detalle = $es_nuevo_lote ? 0 : (int) $item['id_lote_existente'];

                $id_recepcion_detalle = RecepcionesOCData::crear_detalle_recepcion(
                    id_recepcion: $id_recepcion,
                    id_oc_detalle: $id_oc_detalle,
                    id_lote_producto: $id_lote_para_detalle,
                    es_ajuste_stock: !$es_nuevo_lote,
                    cantidad_recepcionada: $cantidad_recep_base / $oc_detalle->contenido_por_presentacion,
                    cantidad_recepcionada_base: $cantidad_recep_base,
                    comentario: null,
                    estado: $estado_det_recep
                );

                // 3. Gestión de Lotes
                if ($es_nuevo_lote) {
                    $contenido_por_presentacion = (float) $oc_detalle->contenido_por_presentacion;
                    $stock_inicial = $cantidad_recep_base / $contenido_por_presentacion;

                    $response = LotesProductosService::crear_lote(
                        id_producto: (int) $oc_detalle->id_producto,
                        id_unidad_medida: (int) $oc_detalle->id_unidad_medida,
                        id_almacen: $id_almacen_recepcionista,
                        id_origen: $id_recepcion_detalle,
                        tabla_origen: 'orden_compra_recepcion_detalle',
                        contenido_por_presentacion: $contenido_por_presentacion,
                        stock_inicial: $stock_inicial,
                        fecha_hora_ingreso: isset($item['fecha_ingreso'])
                        ? Carbon::parse($item['fecha_ingreso'])->toDateTimeString()
                        : $fecha_mysql,
                        descripcion: $item['descripcion'] ?? null,
                        fecha_vencimiento: isset($item['fecha_vencimiento'])
                        ? Carbon::parse($item['fecha_vencimiento'])->toDateTimeString()
                        : null,
                        serie_factura_compra: $serie_comprobante,
                        numero_factura_compra: $numero_comprobante,
                        costo_por_unidad: (float) $oc_detalle->precio_unitario,
                        id_orden_compra_recepcion_detalle: $id_recepcion_detalle,
                        id_orden_compra_detalle: $id_oc_detalle
                    );
                    if (!$response['success']) {
                        throw new \Exception($response['message'] ?? "Error al crear el lote de producto.");
                    }
                    $id_lote_destino = $response['data'];
                    $ids_lotes_nuevos[] = $id_lote_destino;

                    // Vincular el nuevo lote al detalle de recepción
                    RecepcionesOCData::update_detalle_lote($id_recepcion_detalle, $id_lote_destino);
                } else {
                    $id_lote_destino = $id_lote_para_detalle;

                    LotesProductosService::update_stock(
                        id_lote: $id_lote_destino,
                        id_origen: $id_recepcion_detalle,
                        tabla_origen: null,
                        tipo_origen: KardexOrigenMovimiento::Recepcion,
                        tipo_movimiento: KardexTipoMovimiento::Ingreso,
                        cantidad_movimiento_base: $cantidad_recep_base,
                        descripcion: "Ingreso por recepción de Orden de Compra",
                    );
                }

                // 9. Acumular para el post-procesamiento agrupado (estados y logs)
                if (!isset($detallesAgrupados[$id_oc_detalle])) {
                    $detallesAgrupados[$id_oc_detalle] = [
                        'cantidad_recepcionada' => 0,
                        'cantidad_recepcionada_base' => 0,
                        'total_ya_recibido_antes' => $total_ya_recibido_antes
                    ];
                }
                $detallesAgrupados[$id_oc_detalle]['cantidad_recepcionada_base'] += $cantidad_recep_base;
                $detallesAgrupados[$id_oc_detalle]['cantidad_recepcionada'] += ($cantidad_recep_base / $oc_detalle->contenido_por_presentacion);
            }

            // 10. Actualizar Estados y Registrar Logs (Agrupados por OC Detalle)
            foreach ($detallesAgrupados as $id_oc_det => $data) {
                $oc_det = RecepcionesOCData::get_oc_detalle_by_id($id_oc_det);
                if (!$oc_det)
                    continue;

                $total_acumulado = $data['total_ya_recibido_antes'] + $data['cantidad_recepcionada_base'];

                // 11. Actualizar estados post-recepción
                self::actualizar_estados_post_recepcion_oc($id_oc_det);

                // 12. Log de Trazabilidad
                // Si es la primera recepción de este item
                if ($data['total_ya_recibido_antes'] == 0) {
                    OrdenCompraData::registrar_log_detalle(
                        $id_oc_det,
                        $id_empleado_registro,
                        EstadoOrdenCompraDetalleLog::EnRecepcion
                    );
                }

                // Registro de la nueva recepción (cantidad total en esta transacción)
                OrdenCompraData::registrar_log_detalle(
                    $id_oc_det,
                    $id_empleado_registro,
                    EstadoOrdenCompraDetalleLog::NuevaRecepcion,
                    (string) round($data['cantidad_recepcionada'], 2)
                );

                // Si ya finalizó la recepción de este item
                if ($total_acumulado >= $oc_det->cantidad_requerida_base - 0.001) {
                    OrdenCompraData::registrar_log_detalle(
                        $id_oc_det,
                        $id_empleado_registro,
                        EstadoOrdenCompraDetalleLog::RecepcionCompleta
                    );
                }
            }

            $lotes_data = !empty($ids_lotes_nuevos)
                ? LotesProductosData::get_info_to_ticket(ids_lotes: $ids_lotes_nuevos)
                : null;

            if ($tipo_comprobante !== null) {
                OCComprobanteService::registrar_comprobante(
                    id_empleado_registro: $id_empleado_registro,
                    id_orden_compra: $id_orden_compra,
                    tipo_comprobante: $tipo_comprobante,
                    serie: $serie_comprobante,
                    numero: $numero_comprobante,
                    fecha_emision: $fecha_emision_comprobante,
                    observacion: $observacion_comprobante,
                    evidencias: $evidencias_comprobante,
                    moneda: $moneda_comprobante,
                    tipo_cambio_venta_aplicado: $tipo_cambio_comprobante,
                    es_auditable: $es_auditable_comprobante,
                    total_antes_igv: $total_antes_igv_comprobante,
                    total_antes_igv_soles: $total_antes_igv_soles_comprobante,
                    incluye_igv: $incluye_igv_comprobante,
                    porcentaje_igv: $porcentaje_igv_comprobante,
                    monto_igv: $monto_igv_comprobante,
                    monto_igv_soles: $monto_igv_soles_comprobante,
                    total_despues_igv: $total_despues_igv_comprobante,
                    total_despues_igv_soles: $total_despues_igv_soles_comprobante,
                    ids_recepciones: [$id_recepcion]
                );
            }

                return ApiResponse::success($lotes_data, "Recepción de Orden de Compra registrada exitosamente");
            });
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    /**
     * Obtener el historial de recepciones de una OC
     */
    public static function obtener_historial_recepciones(int $id_orden_compra)
    {
        $cabeceras = RecepcionesOCData::get_historial_recepciones($id_orden_compra);
        foreach ($cabeceras as $cab) {
            $cab->evidencias = $cab->evidencias ? json_decode($cab->evidencias) : null;
            $cab->detalles = RecepcionesOCData::get_detalles_recepcion($cab->id_recepcion);
        }

        return ApiResponse::success($cabeceras);
    }

    /**
     * Lógica para actualizar estados después de una recepción de OC
     */
    private static function actualizar_estados_post_recepcion_oc(int $id_oc_detalle)
    {
        $detalle = RecepcionesOCData::get_oc_detalle_by_id($id_oc_detalle);
        if (!$detalle)
            return;

        $total_recibido = RecepcionesOCData::get_cantidad_recepcionada_total_base_detalle($id_oc_detalle);

        // Estado del detalle de OC
        if ($total_recibido <= 0) {
            $nuevo_estado_det = EstadoOrdenCompraDetalle::Pendiente->value;
        } else {
            $nuevo_estado_det = ($total_recibido >= $detalle->cantidad_requerida_base - 0.001)
                ? EstadoOrdenCompraDetalle::RecepcionCompleta->value
                : EstadoOrdenCompraDetalle::EnRecepcion->value;
        }

        RecepcionesOCData::update_oc_detalle_estado($id_oc_detalle, $nuevo_estado_det);

        // Estado de la cabecera de OC
        $id_oc = (int) $detalle->id_orden_compra;
        $todos_detalles = RecepcionesOCData::get_oc_detalles($id_oc);

        $todos_completos = $todos_detalles->every(fn($d) => $d->estado === EstadoOrdenCompraDetalle::RecepcionCompleta->value);
        $algun_recibido = $todos_detalles->contains(fn($d) => $d->estado === EstadoOrdenCompraDetalle::RecepcionCompleta->value || $d->estado === EstadoOrdenCompraDetalle::EnRecepcion->value);

        $nuevo_estado_oc = $todos_completos
            ? EstadoOrdenCompra::Completada->value
            : ($algun_recibido ? EstadoOrdenCompra::EnRecepcion->value : EstadoOrdenCompra::Generada->value);

        RecepcionesOCData::update_oc_estado($id_oc, $nuevo_estado_oc);
    }
}
