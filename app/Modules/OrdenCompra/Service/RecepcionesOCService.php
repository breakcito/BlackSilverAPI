<?php

namespace App\Modules\OrdenCompra\Service;

use App\Data\KardexProductosData;
use App\Data\LotesProductosData;
use App\Modules\OrdenCompra\Data\RecepcionesOCData;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use App\Shared\Enums\OrdenCompra\EstadoOrdenCompra;
use App\Shared\Enums\OrdenCompra\EstadoOrdenCompraDetalle;
use App\Shared\Enums\OrdenCompra\EstadoOrdenCompraRecepcion;
use App\Shared\Enums\OrdenCompra\EstadoOrdenCompraRecepcionDetalle;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecepcionesOCService
{
    /**
     * Registrar una recepción de stock para una Orden de Compra.
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
        /**
         * items: [
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
         * ]
         */
        array $evidencias = []
    ) {
        return DB::transaction(function () use ($id_orden_compra, $id_almacen_recepcionista, $id_empleado_registro, $con_incidencia, $observacion, $fecha_hora_recepcion, $serie_guia, $numero_guia, $items, $evidencias) {
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

            $lotesMap = !empty($ids_lotes_existentes)
                ? collect(LotesProductosData::get_lote_simple_by_id($ids_lotes_existentes))->keyBy('id_lote')
                : collect();

            // 5. Agrupar items por detalle de OC para el registro de orden_compra_recepcion_detalle
            $detallesAgrupados = [];

            // Procesar cada item (lote)
            $ids_lotes_nuevos = [];
            foreach ($items as $item) {
                $id_oc_detalle = (int) $item['id_orden_compra_detalle'];
                $cantidad_recep_base = (float) $item['cantidad_base'];
                $es_nuevo_lote = (bool) $item['es_nuevo_lote'];

                $oc_detalle = RecepcionesOCData::get_oc_detalle_by_id($id_oc_detalle);
                if (!$oc_detalle)
                    continue;

                // 6. Gestión de Lotes
                if ($es_nuevo_lote) {
                    $contenido_por_presentacion = (float) $oc_detalle->contenido_por_presentacion;
                    $stock_inicial = $cantidad_recep_base / $contenido_por_presentacion;

                    $correlativoData = LotesProductosData::get_nuevo_correlativo($id_almacen_recepcionista);
                    $id_lote_destino = LotesProductosData::crear_lote(
                        id_producto: (int) $oc_detalle->id_producto,
                        id_unidad_medida: (int) $oc_detalle->id_unidad_medida,
                        id_almacen: $id_almacen_recepcionista,
                        correlativo: $correlativoData['correlativo'],
                        numero_correlativo: $correlativoData['numero_correlativo'],
                        stock_inicial: $stock_inicial,
                        contenido_por_presentacion: $contenido_por_presentacion,
                        stock_actual_base: $cantidad_recep_base,
                        fecha_hora_ingreso: isset($item['fecha_ingreso'])
                        ? Carbon::parse($item['fecha_ingreso'])->toDateTimeString()
                        : $fecha_mysql,
                        descripcion: $item['descripcion'] ?? "Ingreso por recepción de Orden de Compra",
                        fecha_vencimiento: isset($item['fecha_vencimiento'])
                        ? Carbon::parse($item['fecha_vencimiento'])->toDateTimeString()
                        : null
                    );

                    $ids_lotes_nuevos[] = $id_lote_destino;

                    $stock_anterior = 0;
                    $stock_anterior_base = 0;
                    $nuevo_stock = $stock_inicial;
                    $nuevo_stock_base = $cantidad_recep_base;
                    $contenido_lot = $contenido_por_presentacion;
                } else {
                    $id_lote_destino = (int) $item['id_lote_existente'];
                    $lote_existente = $lotesMap->get($id_lote_destino);

                    $stock_anterior = (float) $lote_existente['stock_actual'];
                    $stock_anterior_base = (float) $lote_existente['stock_actual_base'];
                    $contenido_lot = (float) $lote_existente['contenido_por_presentacion'];

                    $incremento_lote = $cantidad_recep_base / $contenido_lot;
                    $nuevo_stock = $stock_anterior + $incremento_lote;
                    $nuevo_stock_base = $stock_anterior_base + $cantidad_recep_base;

                    LotesProductosData::update_stock($id_lote_destino, $nuevo_stock, $nuevo_stock_base);
                }

                // 7. Registrar Kardex
                KardexProductosData::registrar_kardex(
                    $id_lote_destino,
                    KardexTipoMovimiento::Ingreso,
                    KardexOrigenMovimiento::Recepcion,
                    "Ingreso por recepción de Orden de Compra",
                    $cantidad_recep_base / $contenido_lot,
                    $cantidad_recep_base,
                    $nuevo_stock,
                    $nuevo_stock_base,
                    $id_recepcion,
                    $stock_anterior,
                    $stock_anterior_base
                );

                // 8. Acumular para el detalle de recepción
                if (!isset($detallesAgrupados[$id_oc_detalle])) {
                    $detallesAgrupados[$id_oc_detalle] = [
                        'cantidad_recepcionada' => 0,
                        'cantidad_recepcionada_base' => 0
                    ];
                }
                $detallesAgrupados[$id_oc_detalle]['cantidad_recepcionada_base'] += $cantidad_recep_base;
                // Convertir la base a la unidad de la OC para el registro de detalle
                $detallesAgrupados[$id_oc_detalle]['cantidad_recepcionada'] += ($cantidad_recep_base / $oc_detalle->contenido_por_presentacion);
            }

            // 9. Crear Detalles de Recepción (Agrupados por OC Detalle)
            foreach ($detallesAgrupados as $id_oc_det => $data) {
                // Determinar estado del detalle de recepción
                // (Ojo: aquí el estado es del detalle de la recepción, no de la OC)
                $oc_det = RecepcionesOCData::get_oc_detalle_by_id($id_oc_det);
                $total_ya_recibido = RecepcionesOCData::get_cantidad_recepcionada_total_base_detalle($id_oc_det);
                $total_acumulado = $total_ya_recibido + $data['cantidad_recepcionada_base'];

                $estado_det_recep = ($total_acumulado >= $oc_det->cantidad_requerida_base - 0.001)
                    ? EstadoOrdenCompraRecepcionDetalle::RecepcionCompleta
                    : EstadoOrdenCompraRecepcionDetalle::RecepcionadoParcialmente;

                RecepcionesOCData::crear_detalle_recepcion(
                    id_recepcion: $id_recepcion,
                    id_oc_detalle: $id_oc_det,
                    cantidad_recepcionada: $data['cantidad_recepcionada'],
                    cantidad_recepcionada_base: $data['cantidad_recepcionada_base'],
                    comentario: null,
                    estado: $estado_det_recep
                );

                // 10. Actualizar estados post-recepción
                self::actualizar_estados_post_recepcion_oc($id_oc_det);
            }

            $lotes_data = !empty($ids_lotes_nuevos)
                ? LotesProductosData::get_info_to_ticket(ids_lotes: $ids_lotes_nuevos)
                : null;

            return ApiResponse::success($lotes_data, "Recepción de Orden de Compra registrada exitosamente");
        });
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
