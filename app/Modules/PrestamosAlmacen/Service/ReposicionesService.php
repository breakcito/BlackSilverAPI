<?php

namespace App\Modules\PrestamosAlmacen\Service;

use App\Services\ActivosFijosService;
use App\Services\LotesProductosService;
use App\Shared\Enums\ActivoFijo\MovimientoActivoFijo;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use App\Shared\Responses\ApiResponse;
use App\Data\LotesProductosData;
use App\Modules\PrestamosAlmacen\Data\PrestamosData;
use App\Modules\PrestamosAlmacen\Data\ReposicionesData;
use App\Modules\PrestamosAlmacenAtencion\Service\RecepcionesReposicionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ReposicionesService
{
    /**
     * Obtiene el historial de reposiciones de un préstamo.
     */
    public static function get_historial(int $id_prestamo_almacen)
    {
        $data = ReposicionesData::get_reposiciones_by_prestamo($id_prestamo_almacen);
        foreach ($data as $repo) {
            $repo->evidencias = $repo->evidencias ? json_decode($repo->evidencias) : null;
            $repo->detalles = ReposicionesData::get_detalles_reposicion((int) $repo->id_reposicion);

            // Obtener recepciones de esta reposicion
            $recepcionesResponse = RecepcionesReposicionService::get_historial((int) $repo->id_reposicion);
            $repo->recepciones = $recepcionesResponse['success'] ? $recepcionesResponse['data'] : [];
        }
        return ApiResponse::success($data);
    }

    /**
     * Registra una nueva reposición de stock para un préstamo entre almacenes.
     */
    public static function registrar_reposicion(
        int $id_prestamo_almacen,
        int $id_almacen_entrega,
        int $id_empleado_entrega,
        int $id_personal_recibe,
        string $fecha_hora_reposicion,
        //
        // [{id_prestamo_detalle, id_lote_producto, cantidad_base, cantidad_lote, cantidad_prestamo}]
        array $items,
        //
        ?string $observacion,
        ?array $evidencias = null
    ) {
        return DB::transaction(function () use ($id_prestamo_almacen, $id_almacen_entrega, $id_empleado_entrega, $id_personal_recibe, $fecha_hora_reposicion, $observacion, $items, $evidencias) {
            // 1. Obtener el correlativo del prestamo
            $prestamo = PrestamosData::get_correlativo_by_id($id_prestamo_almacen);

            // 2. Procesar Evidencias si existen
            $evidenciasData = null;
            if (!empty($evidencias)) {
                $evidenciasData = ReposicionesData::guardar_evidencias($evidencias);
            }

            // 3. Generar Correlativo RPS
            $correlativoData = ReposicionesData::get_nuevo_correlativo($id_almacen_entrega);

            // 4. Insertar la cabecera de la reposición
            $id_reposicion = ReposicionesData::crear_reposicion(
                $id_prestamo_almacen,
                $id_almacen_entrega,
                $id_empleado_entrega,
                $id_personal_recibe,
                $correlativoData['correlativo'],
                $correlativoData['numero_correlativo'],
                Carbon::parse($fecha_hora_reposicion)->toDateTimeString(),
                $observacion,
                $evidenciasData,
            );

            // 5. Pre-cargar lotes solo para ítems de productos comunes
            $items_con_lote = array_filter($items, fn($i) => empty($i['id_activo_fijo']));
            $ids_lotes = array_map(fn($i) => (int) $i['id_lote_producto'], $items_con_lote);

            $lotesMap = !empty($ids_lotes)
                ? collect(LotesProductosData::get_lote_simple_by_id($ids_lotes))->keyBy('id_lote')
                : collect();

            // Validar Stock solo para productos comunes
            foreach ($items_con_lote as $item) {
                $lote = $lotesMap->get((int) $item['id_lote_producto']);
                if (!$lote || $lote['stock_actual_base'] < (float) $item['cantidad_base']) {
                    return ApiResponse::error("Stock insuficiente en el lote " . ($lote['correlativo'] ?? 'ID: ' . $item['id_lote_producto']));
                }
            }

            // Procesar cada ítem de la reposición
            foreach ($items as $item) {
                $id_prestamo_detalle = (int) $item['id_prestamo_detalle'];
                $id_activo           = !empty($item['id_activo_fijo']) ? (int) $item['id_activo_fijo'] : null;
                $es_activo           = $id_activo !== null;

                $cantidad_base      = $es_activo ? 1 : (float) $item['cantidad_base'];
                $cantidad_lote      = $es_activo ? 1 : (float) $item['cantidad_lote'];
                $cantidad_prestamo  = $es_activo ? 1 : (float) $item['cantidad_prestamo'];

                // A. Insertar detalle de la reposición
                $id_detalle_reposicion = ReposicionesData::crear_detalle_reposicion(
                    $id_reposicion,
                    $id_prestamo_detalle,
                    $es_activo ? null : (int) $item['id_lote_producto'],
                    $cantidad_base,
                    $cantidad_lote,
                    $cantidad_prestamo,
                    $id_activo
                );

                if ($es_activo) {
                    // B. Activo Fijo: mover ubicación fuera de almacen mientras no se recepcione

                    ActivosFijosService::new_ubicacion(
                        id_activo: $id_activo,
                        tipo_movimiento: MovimientoActivoFijo::DeAlmacenAAlmacen,
                        id_almacen: null,
                        id_mina: null,
                        descripcion: "Reposición N° " . $correlativoData['correlativo'] . " al almacén prestamista",
                        fecha_hora_movimiento: Carbon::parse($fecha_hora_reposicion)->toDateTimeString()
                    );
                } else {
                    // B. Producto Común: actualizar stock del lote y registrar Kardex (Salida)
                    $descripcion_kardex = "Salida por reposición de préstamo N° " . $prestamo->correlativo . " (Ref: " . $correlativoData['correlativo'] . ")";

                    LotesProductosService::update_stock(
                        id_lote: (int) $item['id_lote_producto'],
                        id_origen: $id_detalle_reposicion,
                        tabla_origen: 'prestamo_almacen_reposicion_detalle',
                        tipo_origen: KardexOrigenMovimiento::Reposicion,
                        tipo_movimiento: KardexTipoMovimiento::Salida,
                        cantidad_movimiento_base: $cantidad_base,
                        descripcion: $descripcion_kardex,
                    );
                }

                // C. Incrementar cantidad repuesta en el detalle del préstamo
                PrestamosData::incrementar_cantidad_repuesta($id_prestamo_detalle, $cantidad_prestamo, $cantidad_base);

                // D. Registrar Log de trazabilidad en el detalle del préstamo
                $glosa = "Reposición N° " . $correlativoData['correlativo'] . " registrada por " . $cantidad_prestamo . " productos";
                PrestamosData::crear_log($id_prestamo_detalle, $id_empleado_entrega, $glosa);
            }

            return ApiResponse::success(
                null,
                "Reposición N° " . $correlativoData['correlativo'] . " registrada exitosamente"
            );
        });
    }
}
