<?php

namespace App\Modules\PrestamosAlmacenAtencion\Service;

use App\Data\KardexProductosData;
use App\Data\LotesProductosData;
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

            // 4. Pre-cargar lotes existentes en una sola consulta
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
                $id_repo_det = (int) $item['id_reposicion_detalle'];
                $cantidad_recep_base = (float) $item['cantidad_base'];
                $es_nuevo_lote = (bool) $item['es_nuevo_lote'];

                // Obtener detalle de la reposicion para saber el producto
                $repo_det = RecepcionesReposicionData::get_producto_id_by_repo_det($id_repo_det);

                if (!$repo_det) continue;

                // 5. Gestión de Lotes (Nuevo vs Existente)
                if ($es_nuevo_lote) {
                    $contenido_por_presentacion = (float) ($item['contenido_por_presentacion'] ?? 1);
                    $stock_inicial = $cantidad_recep_base / $contenido_por_presentacion;

                    $correlativoData = LotesProductosData::get_nuevo_correlativo($id_almacen_destino);
                    $id_lote_destino = LotesProductosData::crear_lote(
                        id_producto: (int) $repo_det->id_producto,
                        id_unidad_medida: (int) $item['id_unidad_medida'],
                        id_almacen: $id_almacen_destino,
                        correlativo: $correlativoData['correlativo'],
                        numero_correlativo: $correlativoData['numero_correlativo'],
                        stock_inicial: $stock_inicial,
                        contenido_por_presentacion: $contenido_por_presentacion,
                        stock_actual_base: $cantidad_recep_base,
                        fecha_hora_ingreso: isset($item['fecha_ingreso'])
                            ? Carbon::parse($item['fecha_ingreso'])->toDateTimeString()
                            : $fecha_mysql,
                        descripcion: $item['descripcion'] ?? "Ingreso por recepción de reposición",
                        fecha_vencimiento: isset($item['fecha_vencimiento'])
                            ? Carbon::parse($item['fecha_vencimiento'])->toDateTimeString()
                            : null
                    );

                    $ids_lotes_nuevos[] = $id_lote_destino;

                    // Calcular valores directamente sin re-consultar el lote recién creado
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

                // 6. Registrar Kardex
                KardexProductosData::registrar_kardex(
                    id_lote: $id_lote_destino,
                    id_origen: $id_recepcion,
                    tipo_movimiento: KardexTipoMovimiento::Ingreso,
                    tipo_origen: KardexOrigenMovimiento::Reposicion,
                    descripcion: "Ingreso por recepción de reposición",
                    stock_anterior: $stock_anterior,
                    stock_anterior_base: $stock_anterior_base,
                    cantidad_movimiento: $cantidad_recep_base / $contenido_lot,
                    cantidad_movimiento_base: $cantidad_recep_base,
                    nuevo_stock: $nuevo_stock,
                    nuevo_stock_base: $nuevo_stock_base
                );

                // 7. Crear Detalle de Recepción
                RecepcionesReposicionData::crear_detalle_recepcion($id_recepcion, $id_repo_det, $cantidad_recep_base);

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
        if (!$detalle) return;

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
