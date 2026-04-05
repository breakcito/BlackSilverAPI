<?php

namespace App\Views\SolicitudesReabastecimiento\Service;

use App\Data\KardexProductosData;
use App\Data\LotesProductosData;
use App\Shared\Enums\Kardex\OrigenMovimiento;
use App\Shared\Enums\Kardex\TipoMovimiento;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use App\Views\SolicitudesReabastecimiento\Data\RecepcionesData;
use App\Views\SolicitudesReabastecimiento\Data\RecepcionesPrestamoData;
use App\Views\SolicitudesReabastecimientoAtencion\Data\SolicitudesDetalleData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecepcionesService
{
    /**
     * Registrar una recepción de stock para una entrega específica.
     */
    public static function registrar_recepcion(
        int $id_empleado_registro,
        array $datos_recepcion,
        array $evidencias = []
    ) {
        return DB::transaction(function () use ($id_empleado_registro, $datos_recepcion, $evidencias) {

            $id_entrega = (int) $datos_recepcion['id_reabastecimiento_entrega'];
            $tipo_entrega = $datos_recepcion['tipo_entrega'] ?? 'Solicitud'; // Solicitud | Prestamo
            $con_incidencia = (bool) ($datos_recepcion['con_incidencia'] ?? false);
            $observacion = $datos_recepcion['observacion'] ?? null;
            
            $fecha_hora_recepcion = isset($datos_recepcion['fecha_hora_recepcion']) 
                ? Carbon::parse($datos_recepcion['fecha_hora_recepcion'])->toDateTimeString()
                : now()->toDateTimeString();

            // 1. Guardar evidencias
            $evidenciasJson = null;
            if (!empty($evidencias)) {
                $evidenciasData = ArchivoHelper::guardarArchivos('reabastecimiento/recepciones', $evidencias);
                $evidenciasJson = json_encode($evidenciasData);
            }

            // 2. Crear cabecera de recepción según el tipo
            if ($tipo_entrega === 'Solicitud') {
                $id_recepcion = RecepcionesData::crear_recepcion(
                    $id_entrega,
                    $id_empleado_registro,
                    $fecha_hora_recepcion,
                    $observacion,
                    $evidenciasJson,
                    $con_incidencia
                );
            } else {
                $id_recepcion = RecepcionesPrestamoData::crear_recepcion(
                    $id_entrega,
                    $id_empleado_registro,
                    $fecha_hora_recepcion,
                    $observacion,
                    $evidenciasJson,
                    $con_incidencia
                );
            }

            // 3. Procesar ítems de la recepción
            foreach ($datos_recepcion['items'] as $item) {
                $id_solic_detalle = (int) $item['id_solicitud_reabastecimiento_detalle'];
                $cantidad_recep_base = (float) $item['cantidad_base'];
                $es_nuevo_lote = (bool) $item['es_nuevo_lote'];

                // Obtener detalle de la solicitud para saber el almacén destino y producto
                $detalle_sol = SolicitudesDetalleData::get_detalle_by_id($id_solic_detalle);
                
                $id_almacen_destino = DB::table('solicitud_reabastecimiento')
                    ->where('id', $detalle_sol->id_solicitud_reabastecimiento)
                    ->value('id_almacen_solicitante');

                // 4. Gestión de Lotes (Ajuste vs Nuevo)
                if ($es_nuevo_lote) {
                    $correlativoData = LotesProductosData::get_nuevo_correlativo($id_almacen_destino);
                    $id_lote_destino = LotesProductosData::crear_lote(
                        id_producto: (int) $detalle_sol->id_producto,
                        id_unidad_medida: (int) $item['id_unidad_medida'],
                        id_almacen: $id_almacen_destino,
                        descripcion: $item['descripcion'] ?? "Ingreso por recepción en reabastecimiento",
                        correlativo: $correlativoData['correlativo'],
                        numero_correlativo: $correlativoData['numero_correlativo'],
                        stock_inicial: (float) ($cantidad_recep_base / ($item['contenido_por_presentacion'] ?? 1)),
                        contenido_por_presentacion: (float) ($item['contenido_por_presentacion'] ?? 1),
                        stock_actual_base: $cantidad_recep_base,
                        fecha_hora_ingreso: isset($item['fecha_ingreso']) 
                            ? Carbon::parse($item['fecha_ingreso'])->toDateTimeString() 
                            : $fecha_hora_recepcion,
                        fecha_vencimiento: isset($item['fecha_vencimiento']) 
                            ? Carbon::parse($item['fecha_vencimiento'])->toDateTimeString() 
                            : null
                    );
                    
                    $lote_nuevo = LotesProductosData::get_lote_simple_by_id($id_lote_destino);
                    $stock_anterior = 0;
                    $stock_anterior_base = 0;
                    $nuevo_stock = $lote_nuevo['stock_actual'];
                    $nuevo_stock_base = $lote_nuevo['stock_actual_base'];
                    $contenido_lot = $lote_nuevo['contenido_por_presentacion'];

                } else {
                    $id_lote_destino = (int) $item['id_lote_existente'];
                    $lote_existente = LotesProductosData::get_lote_simple_by_id($id_lote_destino);
                    
                    $stock_anterior = $lote_existente['stock_actual'];
                    $stock_anterior_base = $lote_existente['stock_actual_base'];
                    $contenido_lot = $lote_existente['contenido_por_presentacion'];
                    
                    $incremento_lote = $cantidad_recep_base / $contenido_lot;
                    $nuevo_stock = $stock_anterior + $incremento_lote;
                    $nuevo_stock_base = $stock_anterior_base + $cantidad_recep_base;

                    LotesProductosData::update_stock($id_lote_destino, $nuevo_stock, $nuevo_stock_base);
                }

                // 5. Registrar Kardex
                KardexProductosData::registrar_kardex(
                    $id_lote_destino,
                    TipoMovimiento::Ingreso,
                    OrigenMovimiento::Recepcion,
                    "Ingreso por recepción de entrega en reabastecimiento",
                    $cantidad_recep_base / $contenido_lot,
                    $cantidad_recep_base,
                    $nuevo_stock,
                    $nuevo_stock_base,
                    $id_recepcion,
                    $stock_anterior,
                    $stock_anterior_base
                );

                // 6. Crear Detalle de Recepción
                if ($tipo_entrega === 'Solicitud') {
                    $id_entrega_det = $item['id_entrega_detalle'] ?? null;
                    if (!$id_entrega_det) {
                        $id_entrega_det = DB::table('solicitud_reabastecimiento_entrega_detalle')
                            ->where('id_solicitud_reabastecimiento_entrega', $id_entrega)
                            ->where('id_solicitud_reabastecimiento_detalle', $id_solic_detalle)
                            ->value('id');
                    }
                    RecepcionesData::crear_detalle_recepcion($id_recepcion, $id_entrega_det, $cantidad_recep_base);
                    
                    // 7. Actualizar estados de la entrega (Logística)
                    RecepcionesData::actualizar_estado_entrega_detalle((int)$id_entrega_det);
                } else {
                    $id_entrega_det = $item['id_entrega_detalle'] ?? null;
                    if (!$id_entrega_det) {
                        $id_entrega_det = DB::table('prestamo_almacen_entrega_detalle')
                            ->where('id_prestamo_almacen_entrega', $id_entrega)
                            ->whereIn('id_prestamo_almacen_detalle', function($query) use ($id_solic_detalle) {
                                $query->select('id')->from('prestamo_almacen_detalle')->where('id_solicitud_reabastecimiento_detalle', $id_solic_detalle);
                            })
                            ->value('id');
                    }
                    RecepcionesPrestamoData::crear_detalle_recepcion($id_recepcion, $id_entrega_det, $cantidad_recep_base);

                    // 7. Actualizar estados de la entrega (Préstamo)
                    RecepcionesPrestamoData::actualizar_estado_entrega_detalle((int)$id_entrega_det);
                }
            }

            return ApiResponse::success(null, "Recepción registrada exitosamente");
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
}
