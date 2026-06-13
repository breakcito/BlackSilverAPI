<?php

namespace App\Modules\OrdenesCompraRecepcionTransferencias\Service;

use App\Data\LotesProductosData;
use App\Models\OrdenCompraTransferencia;
use App\Modules\OrdenesCompraRecepcionTransferencias\Data\RecepcionesData;
use App\Modules\OrdenesCompraRecepcionTransferencias\Data\TransferenciasData;
use App\Services\ActivosFijosService;
use App\Services\LotesProductosService;
use App\Shared\Enums\ActivoFijo\MovimientoActivoFijo;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
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
     *   // Para productos comunes:
     *   cantidad_base: float,
     *   es_nuevo_lote: bool,
     *   id_lote_existente: int|null,
     *   descripcion: string|null,
     *   fecha_ingreso: string|null,
     *   fecha_vencimiento: string|null,
     *   // Para activos fijos:
     *   es_activo_fijo: bool,
     *   id_activo_fijo: int,               // activo que se está transfiriendo
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
        return DB::transaction(fn() => self::ejecutar_registro_recepcion(
            id_transferencia: $id_transferencia,
            id_almacen_recepcionista: $id_almacen_recepcionista,
            id_empleado: $id_empleado,
            con_incidencia: $con_incidencia,
            observacion: $observacion,
            fecha_hora_recepcion: $fecha_hora_recepcion,
            items: $items,
            evidencias: $evidencias
        ));
    }

    /**
     * Lógica interna de registro de recepción ejecutada dentro de una transacción.
     */
    private static function ejecutar_registro_recepcion(
        int $id_transferencia,
        int $id_almacen_recepcionista,
        int $id_empleado,
        bool $con_incidencia,
        ?string $observacion,
        ?string $fecha_hora_recepcion,
        array $items,
        array $evidencias
    ) {
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
        $totalesBaseRequest = [];
        $yaRecepcionadoMap = [];
        foreach ($items as $item) {
            $id_det = (int) $item['id_detalle_transferencia'];
            $totalesBaseRequest[$id_det] = ($totalesBaseRequest[$id_det] ?? 0) + (float) $item['cantidad_base'];

            if (!isset($yaRecepcionadoMap[$id_det])) {
                $yaRecepcionadoMap[$id_det] = RecepcionesData::get_cantidad_recepcionada_acumulada($id_det);
            }
        }

        // 6. Procesar items de recepción
        $detallesAgrupados = self::procesar_items_recepcion(
            id_recepcion: $id_recepcion,
            id_almacen_recepcionista: $id_almacen_recepcionista,
            fecha_mysql: $fecha_mysql,
            items: $items,
            totalesBaseRequest: $totalesBaseRequest,
            yaRecepcionadoMap: $yaRecepcionadoMap,
            lotesMap: $lotesMap
        );

        // 7. Determinar estado de la cabecera por los detalles procesados
        $estado_cabecera = EstadoOCTransRecepcion::RecepcionCompleta;

        foreach ($detallesAgrupados as $id_detalle_trans => $data) {
            if ($data['estado_final'] === EstadoOCTransRecepcionDetalle::RecepcionadoParcialmente) {
                $estado_cabecera = EstadoOCTransRecepcion::RecepcionadoParcialmente;
            }
        }

        // 8. Actualizar estado de la cabecera de recepción
        RecepcionesData::update_estado_recepcion($id_recepcion, $estado_cabecera);

        // 9. Actualizar estado de la transferencia (cabecera OCTransferencia)
        self::actualizar_estado_transferencia($id_transferencia);

        return ApiResponse::success(null, 'Recepción de transferencia registrada exitosamente.');
    }

    /**
     * Procesa la lista de items de la recepción y retorna un mapa agrupado de estados y cantidades.
     *
     * @param int $id_recepcion
     * @param int $id_almacen_recepcionista
     * @param string $fecha_mysql
     * @param array $items
     * @param array $totalesBaseRequest
     * @param array $yaRecepcionadoMap
     * @param \Illuminate\Support\Collection $lotesMap
     * @return array
     */
    private static function procesar_items_recepcion(
        int $id_recepcion,
        int $id_almacen_recepcionista,
        string $fecha_mysql,
        array $items,
        array $totalesBaseRequest,
        array $yaRecepcionadoMap,
        \Illuminate\Support\Collection $lotesMap
    ): array {
        $detallesAgrupados = [];

        foreach ($items as $item) {
            $id_detalle_transferencia = (int) $item['id_detalle_transferencia'];
            $es_activo_fijo = !empty($item['es_activo_fijo']);

            // Obtener datos del detalle de transferencia
            $detalle_trans = TransferenciasData::get_detalle_by_id($id_detalle_transferencia);
            if (!$detalle_trans) {
                continue;
            }

            // --- Camino: Activo Fijo ---
            if ($es_activo_fijo) {
                $id_activo = (int) $item['id_activo_fijo'];

                $activo = DB::table('activo_fijo')->where('id', $id_activo)->first();
                $tipo_mov = ($activo && !empty($activo->id_mina))
                    ? MovimientoActivoFijo::DeMinaAAlmacen
                    : MovimientoActivoFijo::DeAlmacenAAlmacen;

                // El activo llega al almacén recepcionista (transferencia entre almacenes)
                ActivosFijosService::new_ubicacion(
                    id_activo: $id_activo,
                    tipo_movimiento: $tipo_mov,
                    id_almacen: $id_almacen_recepcionista,
                    id_mina: null,
                    descripcion: "Recepción de transferencia OC en almacén",
                    fecha_hora_movimiento: $fecha_mysql
                );

                // Registrar detalle de recepción sin lote
                $id_recepcion_detalle = RecepcionesData::crear_recepcion_detalle(
                    id_recepcion: $id_recepcion,
                    id_transferencia_detalle: $id_detalle_transferencia,
                    id_lote_producto: 0,   // no aplica
                    es_ajuste_stock: false,
                    cantidad_recepcionada_base: 1,
                    estado: EstadoOCTransRecepcionDetalle::RecepcionCompleta->value,
                    id_activo_fijo: $id_activo
                );

                // Acumular para post-procesamiento
                if (!isset($detallesAgrupados[$id_detalle_transferencia])) {
                    $detallesAgrupados[$id_detalle_transferencia] = [
                        'cantidad_recepcionada' => 1,
                        'cantidad_recepcionada_base' => 1,
                        'total_ya_recepcionado' => $yaRecepcionadoMap[$id_detalle_transferencia] ?? 0,
                        'estado_final' => EstadoOCTransRecepcionDetalle::RecepcionCompleta,
                    ];
                } else {
                    $detallesAgrupados[$id_detalle_transferencia]['cantidad_recepcionada'] += 1;
                    $detallesAgrupados[$id_detalle_transferencia]['cantidad_recepcionada_base'] += 1;
                }

                continue; // Saltar flujo de lotes
            }

            // --- Camino: Producto Común con Lote ---
            $cantidad_recep_base = (float) $item['cantidad_base'];
            $es_nuevo_lote = (bool) $item['es_nuevo_lote'];

            // 1. Calcular estado previsto del detalle
            $total_ya_recepcionado = $yaRecepcionadoMap[$id_detalle_transferencia];
            $total_final_previsto = $total_ya_recepcionado + $totalesBaseRequest[$id_detalle_transferencia];

            $estado_detalle = ($total_final_previsto >= $detalle_trans->cantidad_transferida_base - 0.001)
                ? EstadoOCTransRecepcionDetalle::RecepcionCompleta
                : EstadoOCTransRecepcionDetalle::RecepcionadoParcialmente;

            // 2. Crear Detalle de Recepción PRIMERO
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

                $lote_origen = null;
                if (!empty($detalle_trans->id_lote_producto)) {
                    $lote_origen = LotesProductosData::get_lote_dinamico_by_id(
                        id_lote: $detalle_trans->id_lote_producto,
                        columnas: [
                            'fecha_vencimiento',
                            'serie_factura_compra',
                            'numero_factura_compra',
                            'costo_por_unidad',
                            'id_orden_compra_detalle',
                            'id_orden_compra_recepcion_detalle',
                            'descripcion'
                        ]
                    );
                }

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
                    descripcion: $item['descripcion'] ?? ($lote_origen ? $lote_origen['descripcion'] : 'Ingreso por recepción de transferencia OC'),
                    fecha_vencimiento: isset($item['fecha_vencimiento']) && $item['fecha_vencimiento']
                    ? Carbon::parse($item['fecha_vencimiento'])->toDateTimeString()
                    : ($lote_origen ? $lote_origen['fecha_vencimiento'] : null),
                    serie_factura_compra: $lote_origen ? $lote_origen['serie_factura_compra'] : null,
                    numero_factura_compra: $lote_origen ? $lote_origen['numero_factura_compra'] : null,
                    costo_por_unidad: $lote_origen ? $lote_origen['costo_por_unidad'] : null,
                    id_orden_compra_recepcion_detalle: $lote_origen ? $lote_origen['id_orden_compra_recepcion_detalle'] : null,
                    id_orden_compra_detalle: $lote_origen ? $lote_origen['id_orden_compra_detalle'] : null
                );
                $id_lote = $response['data'];

                // Vincular el nuevo lote al detalle de recepción
                RecepcionesData::update_detalle_lote($id_recepcion_detalle, $id_lote);
            } else {
                $id_lote = $id_lote_para_detalle;
                $lote_existente = $lotesMap->get($id_lote);
                $contenido = (float) $lote_existente['contenido_por_presentacion'];

                LotesProductosService::update_stock(
                    id_lote: $id_lote,
                    id_origen: $id_recepcion_detalle,
                    tabla_origen: null,
                    tipo_origen: KardexOrigenMovimiento::Recepcion,
                    tipo_movimiento: KardexTipoMovimiento::Ingreso,
                    cantidad_movimiento_base: $cantidad_recep_base,
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

        return $detallesAgrupados;
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
