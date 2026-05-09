<?php

namespace App\Modules\OrdenesCompraRecepcionTransferencias\Service;

use App\Data\LotesProductosData;
use App\Models\OrdenCompraTransferencia;
use App\Modules\OrdenesCompraRecepcionTransferencias\Data\RecepcionesData;
use App\Modules\OrdenesCompraRecepcionTransferencias\Data\TransferenciasData;
use App\Services\LotesProductosService;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\OrdenCompra\EstadoOCTransferencia;
use App\Shared\Enums\OrdenCompra\EstadoOCTransRecepcion;
use App\Shared\Enums\OrdenCompra\EstadoOCTransRecepcionDetalle;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecepcionesService
{
    /**
     * Obtener el historial de recepciones de una transferencia, con sus detalles.
     */
    public static function get_recepciones(int $id_transferencia)
    {
        $recepciones = RecepcionesData::get_recepciones(id_transferencia: $id_transferencia);
        $ids_recepciones = array_map(fn($r) => $r->id_recepcion, $recepciones);

        $detalles_indexados = [];
        if (!empty($ids_recepciones)) {
            $detalles = RecepcionesData::get_detalles_recepcion($ids_recepciones);
            // Indexar por id_recepcion
            foreach ($detalles as $det) {
                $detalles_indexados[$det->id_orden_compra_transferencia_recepcion][] = $det;
            }
        }

        foreach ($recepciones as $recepcion) {
            $recepcion->evidencias = $recepcion->evidencias ? json_decode($recepcion->evidencias) : null;
            $recepcion->detalles = $detalles_indexados[$recepcion->id_recepcion] ?? [];
        }

        return ApiResponse::success($recepciones);
    }

    /**
     * Registrar la recepción de los productos de una transferencia OC.
     * Genera/ajusta lotes en el almacén de destino y registra el kardex.
     * 
     * @param array $items [{
     *   id_detalle_transferencia: int,
     *   cantidad_base: float,
     *   es_nuevo_lote: bool,
     *   id_lote_existente: int|null,
     *   descripcion: string|null,
     *   fecha_ingreso: string|null,
     *   fecha_vencimiento: string|null,
     * }]
     */
    public static function registrar_recepcion(
        int $id_transferencia,
        int $id_almacen_recepcionista,
        int $id_empleado,
        bool $con_incidencia,
        ?string $observacion,
        ?string $fecha_hora_recepcion,
        array $items,
        array $evidencias = []
    ) {
        return DB::transaction(function () use ($id_transferencia, $id_almacen_recepcionista, $id_empleado, $con_incidencia, $observacion, $fecha_hora_recepcion, $items, $evidencias) {
            $fecha_mysql = $fecha_hora_recepcion
                ? Carbon::parse($fecha_hora_recepcion)->toDateTimeString()
                : now()->toDateTimeString();

            // 1. Guardar evidencias
            $evidenciasJson = null;
            if (!empty($evidencias)) {
                $evidenciasData = ArchivoHelper::guardarArchivos('oc-trans-recepciones', $evidencias);
                $evidenciasJson = json_encode($evidenciasData);
            }

            // 2. Correlativo por transferencia
            $numero_correlativo = RecepcionesData::get_nuevo_correlativo($id_transferencia);

            // 3. Crear cabecera de recepción
            $id_recepcion = RecepcionesData::crear_recepcion(
                id_transferencia: $id_transferencia,
                id_empleado: $id_empleado,
                numero_correlativo: $numero_correlativo,
                fecha_hora_recepcion: $fecha_mysql,
                observacion: $observacion,
                evidencias: $evidenciasJson,
                con_incidencia: $con_incidencia,
                estado: EstadoOCTransRecepcion::RecepcionCompleta // se recalcula al final
            );

            // 4. Pre-cargar lotes existentes en bloque
            $ids_lotes_existentes = collect($items)
                ->where('es_nuevo_lote', false)
                ->map(fn($i) => (int) $i['id_lote_existente'])
                ->filter()
                ->values()
                ->all();

            $lotesMap = !empty($ids_lotes_existentes)
                ? collect(LotesProductosData::get_lote_simple_by_id($ids_lotes_existentes))->keyBy('id_lote')
                : collect();

            // 5. Agrupar y pre-calcular totales de la solicitud por detalle de transferencia
            $detallesAgrupados = [];
            $totalesBaseRequest = [];
            $yaRecepcionadoMap = [];
            foreach ($items as $item) {
                $id_det = (int) $item['id_detalle_transferencia'];
                $totalesBaseRequest[$id_det] = ($totalesBaseRequest[$id_det] ?? 0) + (float) $item['cantidad_base'];

                if (!isset($yaRecepcionadoMap[$id_det])) {
                    $yaRecepcionadoMap[$id_det] = RecepcionesData::get_cantidad_recepcionada_acumulada($id_det);
                }
            }

            // Procesar cada item (lote)
            foreach ($items as $item) {
                $id_detalle_transferencia = (int) $item['id_detalle_transferencia'];
                $cantidad_recep_base = (float) $item['cantidad_base'];
                $es_nuevo_lote = (bool) $item['es_nuevo_lote'];

                // Obtener datos del detalle de transferencia para conocer id_producto y unidades
                $detalle_trans = TransferenciasData::get_detalle_by_id($id_detalle_transferencia);
                if (!$detalle_trans)
                    continue;

                // 1. Calcular estado previsto del detalle
                $total_ya_recepcionado = $yaRecepcionadoMap[$id_detalle_transferencia];
                $total_final_previsto = $total_ya_recepcionado + $totalesBaseRequest[$id_detalle_transferencia];

                $estado_detalle = ($total_final_previsto >= $detalle_trans->cantidad_transferida_base - 0.001)
                    ? EstadoOCTransRecepcionDetalle::RecepcionCompleta
                    : EstadoOCTransRecepcionDetalle::RecepcionadoParcialmente;

                // 2. Crear Detalle de Recepción PRIMERO
                // Si es nuevo lote, el ID de lote es 0 temporalmente
                $id_lote_para_detalle = $es_nuevo_lote ? 0 : (int) $item['id_lote_existente'];

                $id_recepcion_detalle = RecepcionesData::crear_recepcion_detalle(
                    id_recepcion: $id_recepcion,
                    id_transferencia_detalle: $id_detalle_transferencia,
                    id_lote_producto: $id_lote_para_detalle,
                    es_ajuste_stock: !$es_nuevo_lote,
                    cantidad_recepcionada_base: $cantidad_recep_base,
                    estado: $estado_detalle->value
                );

                // 3. Gestión de lotes
                if ($es_nuevo_lote) {
                    $contenido = (float) $detalle_trans->contenido_por_presentacion_lot;
                    $stock_inicial = $contenido > 0
                        ? $cantidad_recep_base / $contenido
                        : $cantidad_recep_base;

                    $response = LotesProductosService::crear_lote(
                        id_producto: (int) $detalle_trans->id_producto,
                        id_unidad_medida: (int) $detalle_trans->id_unidad_medida_lot,
                        id_almacen: $id_almacen_recepcionista,
                        id_origen: $id_recepcion_detalle,
                        tabla_origen: 'orden_compra_transferencia_recepcion_detalle',
                        contenido_por_presentacion: $contenido > 0 ? $contenido : 1,
                        stock_inicial: $stock_inicial,
                        fecha_hora_ingreso: isset($item['fecha_ingreso'])
                        ? Carbon::parse($item['fecha_ingreso'])->toDateTimeString()
                        : $fecha_mysql,
                        descripcion: $item['descripcion'] ?? 'Ingreso por recepción de transferencia OC',
                        fecha_vencimiento: isset($item['fecha_vencimiento']) && $item['fecha_vencimiento']
                        ? Carbon::parse($item['fecha_vencimiento'])->toDateTimeString()
                        : null
                    );
                    $id_lote = $response['data'];

                    // Vincular el nuevo lote al detalle de recepción
                    RecepcionesData::update_detalle_lote($id_recepcion_detalle, $id_lote);
                } else {
                    $id_lote = $id_lote_para_detalle;
                    $lote_existente = $lotesMap->get($id_lote);
                    $contenido = (float) $lote_existente['contenido_por_presentacion'];
                    $nuevo_stock_base = (float) $lote_existente['stock_actual_base'] + $cantidad_recep_base;

                    LotesProductosService::update_stock(
                        id_lote: $id_lote,
                        id_origen: $id_recepcion_detalle,
                        tabla_origen: null,
                        tipo_origen: KardexOrigenMovimiento::Recepcion,
                        nuevo_stock_base: $nuevo_stock_base,
                        descripcion: 'Ingreso por recepción de transferencia OC',
                    );
                }

                // 9. Acumular para el post-procesamiento agrupado
                if (!isset($detallesAgrupados[$id_detalle_transferencia])) {
                    $detallesAgrupados[$id_detalle_transferencia] = [
                        'cantidad_recepcionada_base' => 0,
                        'estado_final' => $estado_detalle
                    ];
                }
                $detallesAgrupados[$id_detalle_transferencia]['cantidad_recepcionada_base'] += $cantidad_recep_base;
            }

            // 10. Determinar estado de la cabecera por los detalles procesados
            $estado_cabecera = EstadoOCTransRecepcion::RecepcionCompleta;

            foreach ($detallesAgrupados as $id_detalle_trans => $data) {
                if ($data['estado_final'] === EstadoOCTransRecepcionDetalle::RecepcionadoParcialmente) {
                    $estado_cabecera = EstadoOCTransRecepcion::RecepcionadoParcialmente;
                }
            }

            // 10. Actualizar estado de la cabecera de recepción
            RecepcionesData::update_estado_recepcion($id_recepcion, $estado_cabecera);

            // 11. Actualizar estado de la transferencia (cabecera OCTransferencia)
            self::actualizar_estado_transferencia($id_transferencia);

            return ApiResponse::success(null, 'Recepción de transferencia registrada exitosamente.');
        });
    }

    /**
     * Recalcula y actualiza el estado de la transferencia en función
     * de cuánto se ha recepcionado en total vs lo transferido.
     */
    private static function actualizar_estado_transferencia(int $id_transferencia): void
    {
        $detalles = TransferenciasData::get_detalles_transferencia($id_transferencia);
        if (empty($detalles))
            return;

        $todos_completos = true;
        $alguno_recepcionado = false;

        foreach ($detalles as $det) {
            $acumulado = RecepcionesData::get_cantidad_recepcionada_acumulada_por_transferencia_detalle(
                (int) $det->id_transferencia_detalle
            );

            if ($acumulado > 0.001) {
                $alguno_recepcionado = true;
            }

            if ($acumulado < $det->cantidad_transferida_base - 0.001) {
                $todos_completos = false;
            }
        }

        if ($todos_completos) {
            $nuevo_estado = EstadoOCTransferencia::RecepcionCompleta;
        } elseif ($alguno_recepcionado) {
            $nuevo_estado = EstadoOCTransferencia::RecepcionadoParcialmente;
        } else {
            $nuevo_estado = EstadoOCTransferencia::EnDespacho;
        }

        OrdenCompraTransferencia::where('id', $id_transferencia)
            ->update(['estado' => $nuevo_estado->value]);
    }
}
