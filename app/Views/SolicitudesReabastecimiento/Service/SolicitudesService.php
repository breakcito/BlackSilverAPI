<?php

namespace App\Views\SolicitudesReabastecimiento\Service;

use App\Shared\Enums\SolicitudReabastecimiento\EstadoDetalleEntrega;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalle;
use App\Shared\Responses\ApiResponse;
use App\Views\SolicitudesReabastecimiento\Data\SolicitudesData;
use App\Views\SolicitudesReabastecimiento\Data\SolicitudesDetalleData;
use Illuminate\Support\Facades\DB;

class SolicitudesService
{

    // Obtener todas la lista de solicitudes hechas por el empleado
    public static function get_solicitudes(int $id_empleado, int $mes, int $yearcito)
    {
        $data = SolicitudesData::get_solicitudes(
            id_empleado: $id_empleado,
            mes: $mes,
            yearcito: $yearcito
        );

        return ApiResponse::success($data);
    }

    // Registrar una solicitud y sus detalles
    public static function crear_solicitud(
        int $id_almacen_solicitante,
        int $id_empleado_solicitante,
        string $premura,
        ?string $observacion,
        ?string $fecha_entrega_requerida,
        // id_producto, id_unidad_medida, cantidad_solicitada, 
        // contenido_por_presentacion 
        // comentario
        array $detalles
    ) {
        // 1. Generar Correlativo
        $correlativoData = SolicitudesData::get_nuevo_correlativo($id_almacen_solicitante);
        $correlativo = $correlativoData['correlativo'];
        $numero_correlativo = $correlativoData['numero_correlativo'];

        // 2. Crear cabecera
        $id_solicitud = SolicitudesData::crear_solicitud(
            $id_almacen_solicitante,
            $id_empleado_solicitante,
            $correlativo,
            $numero_correlativo,
            $observacion,
            $premura,
            $fecha_entrega_requerida
        );

        // 3. Crear detalles
        foreach ($detalles as $detalle) {
            $id_producto = (int) $detalle['id_producto'];
            $id_unidad_medida = (int) $detalle['id_unidad_medida'];
            $cantidad_solicitada = (float) $detalle['cantidad_solicitada'];
            $contenido_por_presentacion = (float) $detalle['contenido_por_presentacion'];
            $cantidad_solicitada_base = $cantidad_solicitada * $contenido_por_presentacion;
            $comentario = $detalle['comentario'] ?? null;

            $id_solicitud_detalle = SolicitudesDetalleData::crear_detalle_solicitud(
                $id_solicitud,
                $id_producto,
                $id_unidad_medida,
                $cantidad_solicitada,
                $contenido_por_presentacion,
                $cantidad_solicitada_base,
                $comentario
            );

            $estadoEnum = EstadoSolicitudDetalle::EsperandoAprobacion;
            SolicitudesDetalleData::insert_detalle_log(
                (int)$id_solicitud_detalle,
                $id_empleado_solicitante,
                $estadoEnum->getGlosa($comentario),
                $estadoEnum->value
            );
        }

        return ApiResponse::success(
            SolicitudesData::get_solicitud_by_id($id_solicitud),
            'Solicitud generada correctamente'
        );
    }

    // Obtener los detalles de una solicitud
    public static function get_detalles_solicitud(int $id_solicitud)
    {
        $detalles = SolicitudesDetalleData::get_detalles_solicitud($id_solicitud);
        return ApiResponse::success($detalles);
    }


    // Obtener la trazabildiad de un detalle
    public static function get_trazabilidad_by_detalle(int $id_solicitud_detalle)
    {
        $detalles = SolicitudesDetalleData::get_trazabilidad_by_detalle($id_solicitud_detalle);
        return ApiResponse::success($detalles);
    }

    // Obtener el historial de entregas de una solicitud y sus detalles
    public static function get_historial_entregas(int $id_solicitud)
    {
        $data = SolicitudesData::get_historial_entregas($id_solicitud);

        foreach ($data as $entrega) {
            $entrega->detalles = SolicitudesData::get_detalles_entrega((int) $entrega->id_reabastecimiento_entrega);
        }

        return ApiResponse::success($data);
    }

    public static function get_lotes_destino_disponibles(int $id_reabastecimiento_entrega)
    {
        $lotes = SolicitudesData::get_lotes_destino_disponibles($id_reabastecimiento_entrega);
        return ApiResponse::success($lotes);
    }

    // Recibir múltiples ítems de entrega a la vez
    public static function recibir_entregas(int $id_reabastecimiento_entrega, array $items)
    {
        try {
            DB::beginTransaction();

            foreach ($items as $item) {
                $id_solicitud_detalle = (int) $item['id_solicitud_reabastecimiento_detalle'];
                $es_nuevo_lote = (bool) $item['es_nuevo_lote'];

                // 1. Obtener la infomación del detalle de la entrega
                $detalleEntrega = SolicitudesData::get_entrega_detalle_info($id_reabastecimiento_entrega, $id_solicitud_detalle);

                if (!$detalleEntrega) {
                    return ApiResponse::error('Uno de los detalles de entrega no existe');
                }

                // Ya está recibido?
                if ($detalleEntrega->estado_entrega_detalle === EstadoDetalleEntrega::Recibido->value) {
                    continue; // Skip ya recibidos, o podríamos lanzar error, mejor return error si el usuario eligió enviarlo
                    // Pero para mayor robustez, lanzamos error si intenta procesar algo ya recibido o simplemente lo ignoramos.
                    // return ApiResponse::error('El ítem ' . $detalleEntrega->producto . ' ya ha sido recibido previamente');
                }

                if ($detalleEntrega->estado_entrega_detalle === EstadoDetalleEntrega::Anulado->value) {
                    continue;
                }

                $cantidad_lote = (float) $detalleEntrega->cantidad_lote;
                $cantidad_base = (float) $detalleEntrega->cantidad_base;
                $id_producto = (int) $detalleEntrega->id_producto;
                $id_unidad_medida = (int) $detalleEntrega->id_unidad_medida;
                $id_almacen = (int) $detalleEntrega->id_almacen_solicitante;
                $correlativo_entrega = $detalleEntrega->correlativo_entrega;
                $correlativo_solicitud = $detalleEntrega->correlativo_solicitud;

                $descripcion_kardex = "Recepción de la entrega " . $correlativo_entrega . " por solicitud " . $correlativo_solicitud;

                $id_lote_producto = null;

                if ($es_nuevo_lote) {
                    $fecha_vencimiento = !empty($item['fecha_vencimiento']) ? date('Y-m-d', strtotime($item['fecha_vencimiento'])) : null;

                    $id_lote_producto = SolicitudesData::registrar_recepcion_lote_nuevo(
                        $id_producto,
                        $id_unidad_medida,
                        $id_almacen,
                        $fecha_vencimiento,
                        $cantidad_lote,
                        $cantidad_base
                    );
                } else {
                    $id_lote_existente = (int) $item['id_lote_existente'];
                    $lote = SolicitudesData::registrar_recepcion_lote_existente(
                        $id_lote_existente,
                        $cantidad_lote,
                        $cantidad_base
                    );
                    if (!$lote) {
                        DB::rollBack();
                        return ApiResponse::error('No se encontró el lote especificado para ajustar el stock');
                    }
                    $id_lote_producto = $id_lote_existente;
                }

                // Registrar en KardexProducto
                SolicitudesData::registrar_kardex_recepcion(
                    $id_lote_producto,
                    $id_reabastecimiento_entrega,
                    $cantidad_lote,
                    $cantidad_base,
                    $descripcion_kardex
                );

                // Actualizar estado del detalle de la entrega a "Recibido"
                SolicitudesData::marcar_entrega_detalle_como_recibido($detalleEntrega->id_entrega_detalle);
            }

            // Verificar si todos los detalles de la entrega fueron recibidos, si es así marcar la entrega como Recibida
            SolicitudesData::verificar_y_completar_entrega($id_reabastecimiento_entrega);

            DB::commit();

            return ApiResponse::success(null, 'Ítems de entrega recibidos y stock actualizado correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Ocurrió un error al procesar las recepciones: ' . $e->getMessage());
        }
    }
}
