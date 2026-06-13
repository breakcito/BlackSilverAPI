<?php

namespace App\Modules\PrestamosAlmacenAtencion\Service;


use App\Services\ActivosFijosService;
use App\Shared\Enums\ActivoFijo\MovimientoActivoFijo;
use App\Data\LotesProductosData;
use App\Services\LotesProductosService;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use App\Modules\PrestamosAlmacenAtencion\Data\RecepcionesReposicionData;
use App\Shared\Enums\PrestamoAlmacen\EstadoPrestamoReposicion;
use App\Shared\Enums\PrestamoAlmacen\EstadoPrestamoReposicionDetalle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecepcionesReposicionService
{
    /**
     * Registrar una recepción de stock para una reposición de préstamo.
     * 
     * @param array $items Contiene:
     *   - 'id_reposicion_detalle'
     *   - 'cantidad_base'
     *   - 'es_nuevo_lote'
     *   - 'id_lote_existente' (opcional)
     *   - 'id_unidad_medida'
     *   - 'contenido_por_presentacion'
     *   - 'descripcion'
     *   - 'fecha_vencimiento'
     *   - 'fecha_ingreso'
     *   - 'unidad_abv'
     */
    public static function registrar_recepcion(
        int $id_reposicion,
        int $id_empleado_registro,
        bool $con_incidencia,
        ?string $observacion,
        ?string $fecha_hora_recepcion,
        array $items,
        array $evidencias = []
    ) {
        return DB::transaction(function () use ($id_reposicion, $id_empleado_registro, $con_incidencia, $observacion, $fecha_hora_recepcion, $items, $evidencias) {

            $fecha_mysql = $fecha_hora_recepcion
                ? Carbon::parse($fecha_hora_recepcion)->toDateTimeString()
                : now()->toDateTimeString();

            // 1. Guardar evidencias
            $evidenciasJson = null;
            if (!empty($evidencias)) {
                $evidenciasData = ArchivoHelper::guardarArchivos('prestamos/recepciones_reposicion', $evidencias);
                $evidenciasJson = json_encode($evidenciasData);
            }

            // 2. Crear cabecera de recepción
            $id_recepcion = RecepcionesReposicionData::crear_recepcion(
                $id_reposicion,
                $id_empleado_registro,
                $fecha_mysql,
                $observacion,
                $evidenciasJson,
                $con_incidencia
            );

            // 3. Obtener el almacenamiento del prestamista (destino)
            $reposicion = RecepcionesReposicionData::get_reposicion_info_with_almacen($id_reposicion);

            if (!$reposicion) {
                throw new \Exception("Reposición no encontrada.");
            }

            $id_almacen_destino = (int) $reposicion->id_almacen_prestamista;

            // 4. Procesar ítems de la recepción
            $ids_lotes_nuevos = [];
            foreach ($items as $item) {
                $id_repo_det = (int) $item['id_reposicion_detalle'];
                $cantidad_recep_base = (float) $item['cantidad_base'];
                $id_activo = !empty($item['id_activo_fijo']) ? (int) $item['id_activo_fijo'] : null;
                $es_activo = $id_activo !== null;

                if ($es_activo) {
                    // --- Camino: Activo Fijo ---
                    // El activo llega de vuelta al almacén prestamista (destino)
                    ActivosFijosService::new_ubicacion(
                        id_activo: $id_activo,
                        tipo_movimiento: MovimientoActivoFijo::DeAlmacenAAlmacen,
                        id_almacen: $id_almacen_destino,
                        id_mina: null,
                        descripcion: "Recepción de activo en reposición de préstamo",
                        fecha_hora_movimiento: $fecha_mysql
                    );

                    RecepcionesReposicionData::crear_detalle_recepcion(
                        id_recepcion: $id_recepcion,
                        id_reposicion_detalle: $id_repo_det,
                        id_lote_producto: 0, // no aplica
                        es_ajuste_stock: false,
                        cantidad_recep_base: 1,
                        estado: EstadoPrestamoReposicionDetalle::RecepcionCompleta,
                        id_activo_fijo: $id_activo
                    );

                    self::actualizar_estados_post_recepcion($id_repo_det);
                    continue;
                }

                $es_nuevo_lote = (bool) $item['es_nuevo_lote'];

                // Obtener detalle de la reposicion para saber el producto y el lote de origen
                $repo_det = RecepcionesReposicionData::get_detalle_by_id_para_recepcion(id_detalle: $id_repo_det);

                if (!$repo_det)
                    continue;

                // 1. Crear Detalle de Recepción PRIMERO
                $id_lote_para_detalle = $es_nuevo_lote ? 0 : (int) $item['id_lote_existente'];

                $id_recepcion_detalle = RecepcionesReposicionData::crear_detalle_recepcion(
                    id_recepcion: $id_recepcion,
                    id_reposicion_detalle: $id_repo_det,
                    id_lote_producto: $id_lote_para_detalle,
                    es_ajuste_stock: !$es_nuevo_lote,
                    cantidad_recep_base: $cantidad_recep_base,
                    estado: $es_nuevo_lote ? EstadoPrestamoReposicionDetalle::RecepcionCompleta : EstadoPrestamoReposicionDetalle::RecepcionadoParcialmente
                );

                // 2. Gestión de Lotes (Nuevo vs Existente)
                if ($es_nuevo_lote) {
                    $contenido_por_presentacion = (float) ($item['contenido_por_presentacion'] ?? 1);
                    $stock_inicial = $cantidad_recep_base / $contenido_por_presentacion;

                    $lote_origen = null;
                    if ($repo_det && !empty($repo_det->id_lote_producto)) {
                        $lote_origen = LotesProductosData::get_lote_dinamico_by_id(
                            id_lote: $repo_det->id_lote_producto,
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
                        id_producto: (int) $repo_det->id_producto,
                        id_unidad_medida: (int) $item['id_unidad_medida'],
                        id_almacen: $id_almacen_destino,
                        id_origen: $id_recepcion_detalle,
                        tabla_origen: 'prestamo_almacen_reposicion_recepcion_detalle',
                        contenido_por_presentacion: $contenido_por_presentacion,
                        stock_inicial: $stock_inicial,
                        fecha_hora_ingreso: isset($item['fecha_ingreso'])
                        ? Carbon::parse($item['fecha_ingreso'])->toDateTimeString()
                        : $fecha_mysql,
                        descripcion: $item['descripcion'] ?? ($lote_origen ? $lote_origen['descripcion'] : "Ingreso por recepción de reposición"),
                        fecha_vencimiento: isset($item['fecha_vencimiento']) && $item['fecha_vencimiento']
                        ? Carbon::parse($item['fecha_vencimiento'])->toDateTimeString()
                        : ($lote_origen ? $lote_origen['fecha_vencimiento'] : null),
                        serie_factura_compra: $lote_origen ? $lote_origen['serie_factura_compra'] : null,
                        numero_factura_compra: $lote_origen ? $lote_origen['numero_factura_compra'] : null,
                        costo_por_unidad: $lote_origen ? $lote_origen['costo_por_unidad'] : null,
                        id_orden_compra_recepcion_detalle: $lote_origen ? $lote_origen['id_orden_compra_recepcion_detalle'] : null,
                        id_orden_compra_detalle: $lote_origen ? $lote_origen['id_orden_compra_detalle'] : null
                    );
                    $id_lote_destino = $response['data'];
                    $ids_lotes_nuevos[] = $id_lote_destino;

                    // Vincular el nuevo lote al detalle de recepción
                    RecepcionesReposicionData::update_detalle_lote($id_recepcion_detalle, $id_lote_destino);
                } else {
                    $id_lote_destino = $id_lote_para_detalle;

                    LotesProductosService::update_stock(
                        id_lote: $id_lote_destino,
                        id_origen: $id_recepcion_detalle,
                        tabla_origen: null,
                        tipo_origen: KardexOrigenMovimiento::Reposicion,
                        tipo_movimiento: KardexTipoMovimiento::Ingreso,
                        cantidad_movimiento_base: $cantidad_recep_base,
                        descripcion: "Ingreso por recepción de reposición",
                    );
                }

                // 8. Actualizar estados del detalle de reposición
                self::actualizar_estados_post_recepcion($id_repo_det);
            }

            $lotes_data = !empty($ids_lotes_nuevos)
                ? LotesProductosData::get_info_to_ticket(ids_lotes: $ids_lotes_nuevos)
                : null;

            return ApiResponse::success($lotes_data, "Recepción de reposición registrada exitosamente");
        });
    }

    /**
     * Obtener el historial de recepciones de una reposición.
     */
    public static function get_historial(int $id_reposicion)
    {
        $recepciones = RecepcionesReposicionData::get_historial_recepciones($id_reposicion);

        foreach ($recepciones as $rec) {
            $rec->evidencias = $rec->evidencias ? json_decode($rec->evidencias) : null;
            $rec->detalles = RecepcionesReposicionData::get_detalles_recepcion($rec->id_recepcion);
        }

        return ApiResponse::success($recepciones);
    }

    /**
     * Actualiza el estado de la reposicion y sus detalles después de una recepción.
     */
    private static function actualizar_estados_post_recepcion(int $id_reposicion_detalle)
    {
        $detalle = RecepcionesReposicionData::get_reposicion_detalle_by_id($id_reposicion_detalle);
        if (!$detalle)
            return;

        $total_recepcionado = RecepcionesReposicionData::get_cantidad_recepcionada_total_base_detalle($id_reposicion_detalle);

        // Estado detalle
        $nuevo_estado_det = ($total_recepcionado >= $detalle->cantidad_base - 0.0001) ? EstadoPrestamoReposicionDetalle::RecepcionCompleta : EstadoPrestamoReposicionDetalle::RecepcionadoParcialmente;
        RecepcionesReposicionData::update_reposicion_detalle_estado($id_reposicion_detalle, $nuevo_estado_det->value);

        // Estado cabecera
        $id_reposicion = (int) $detalle->id_prestamo_almacen_reposicion;
        $todos_detalles = RecepcionesReposicionData::get_reposicion_detalles($id_reposicion);

        $todos_recibidos = true;
        $algun_recibido = false;

        foreach ($todos_detalles as $d) {
            if ($d->estado === EstadoPrestamoReposicionDetalle::RecepcionCompleta->value) {
                $algun_recibido = true;
            } else {
                $todos_recibidos = false;
                if ($d->estado === EstadoPrestamoReposicionDetalle::RecepcionadoParcialmente->value) {
                    $algun_recibido = true;
                }
            }
        }

        $nuevo_estado_cab = $todos_recibidos ? EstadoPrestamoReposicion::RecepcionCompleta : ($algun_recibido ? EstadoPrestamoReposicion::RecepcionadoParcialmente : EstadoPrestamoReposicion::EnDespacho);
        RecepcionesReposicionData::update_reposicion_estado($id_reposicion, $nuevo_estado_cab->value);
    }

    /**
     * Obtiene los detalles de una reposición para el proceso de recepción.
     */
    public static function get_detalles_para_recepcion(int $id_reposicion)
    {
        return RecepcionesReposicionData::get_detalles_para_recepcion($id_reposicion);
    }
}
