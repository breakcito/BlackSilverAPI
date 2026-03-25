<?php

namespace App\Views\SolicitudesReabastecimiento\Service;

use App\Models\SolicitudReabastecimiento;
use App\Models\SolicitudReabastecimientoEntrega;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoDetalleEntrega;
use App\Shared\Responses\ApiResponse;
use App\Views\SolicitudesReabastecimiento\Data\EntregasData;
use App\Views\SolicitudesReabastecimiento\Data\RecepcionData;
use Illuminate\Support\Facades\DB;

class EntregasService
{
    // Obtener el historial de entregas de una solicitud y sus detalles
    public static function get_historial_entregas(int $id_solicitud)
    {
        $data = EntregasData::get_historial_entregas($id_solicitud);

        foreach ($data as $entrega) {
            $entrega->evidencias = $entrega->evidencias ? json_decode($entrega->evidencias) : null;
            $entrega->detalles = EntregasData::get_detalles_entrega((int) $entrega->id_reabastecimiento_entrega);
        }

        return ApiResponse::success($data);
    }

    // Recibir múltiples ítems de entrega a la vez
    public static function recibir_entregas(int $id_reabastecimiento_entrega, array $items)
    {
        return self::recibir_entregas_bulk([
            [
                'id_reabastecimiento_entrega' => $id_reabastecimiento_entrega,
                'items' => $items
            ]
        ]);
    }

    // Recibir ítems de múltiples entregas a la vez (Recepción Global)
    public static function recibir_entregas_bulk(array $recepciones)
    {
        try {
            DB::beginTransaction();

            foreach ($recepciones as $recepcion) {
                $id_reabastecimiento_entrega = (int) $recepcion['id_reabastecimiento_entrega'];
                $items = $recepcion['items'];

                $entrega = SolicitudReabastecimientoEntrega::find($id_reabastecimiento_entrega);
                if (!$entrega) {
                    throw new \Exception("La entrega ID {$id_reabastecimiento_entrega} no existe");
                }

                $solicitud = SolicitudReabastecimiento::find($entrega->id_solicitud_reabastecimiento);
                if (!$solicitud) {
                    throw new \Exception("La solicitud asociada no existe para la entrega " . $entrega->correlativo);
                }

                $correlativo_entrega = $entrega->correlativo;
                $correlativo_solicitud = $solicitud->correlativo;
                $id_almacen = $solicitud->id_almacen_solicitante;

                // Obtener detalles para mapeo, agrupados por id_solicitud_reabastecimiento_detalle
                $detalles_entrega = EntregasData::get_detalles_entrega($id_reabastecimiento_entrega);
                $detalles_grouped = collect($detalles_entrega)->groupBy('id_solicitud_reabastecimiento_detalle');

                foreach ($items as $item) {
                    $id_solicitud_detalle = (int) $item['id_solicitud_reabastecimiento_detalle'];
                    $es_nuevo_lote = (bool) $item['es_nuevo_lote'];

                    if (!$detalles_grouped->has($id_solicitud_detalle)) {
                        throw new \Exception('Uno de los detalles de entrega no existe');
                    }

                    $db_detalles = $detalles_grouped->get($id_solicitud_detalle);
                    // Tomar cualquiera para obtener datos del producto (unidad base, etc)
                    $detalleBase = $db_detalles->first();

                    $cantidad_base_ingresada = (float) $item['cantidad_base'];
                    $id_producto = (int) $detalleBase->id_producto;
                    $descripcion_kardex = "Recepción de la entrega " . $correlativo_entrega . " por solicitud " . $correlativo_solicitud;

                    $id_lote_producto = null;
                    $cantidad_kardex_lote = 0;

                    if ($es_nuevo_lote) {
                        $fecha_vencimiento = !empty($item['fecha_vencimiento']) ? date('Y-m-d', strtotime($item['fecha_vencimiento'])) : null;
                        $fecha_ingreso = !empty($item['fecha_ingreso']) ? date('Y-m-d H:i:s', strtotime($item['fecha_ingreso'])) : null;
                        $descripcion = !empty($item['descripcion']) ? $item['descripcion'] : null;
                        
                        $id_unidad_medida_solicitada = !empty($item['id_unidad_medida']) ? (int)$item['id_unidad_medida'] : (int) $detalleBase->id_unidad_medida_solicitada;
                        $contenido_solicitado = !empty($item['contenido_por_presentacion']) ? (float)$item['contenido_por_presentacion'] : (float) $detalleBase->contenido_por_presentacion_solicitado;

                        $cantidad_lote_ingresada = $cantidad_base_ingresada / $contenido_solicitado;

                        $id_lote_producto = RecepcionData::registrar_recepcion_lote_nuevo(
                            $id_producto,
                            $id_unidad_medida_solicitada,
                            $id_almacen,
                            $fecha_vencimiento,
                            $cantidad_lote_ingresada,
                            $cantidad_base_ingresada,
                            $contenido_solicitado,
                            $descripcion,
                            $fecha_ingreso
                        );
                        $cantidad_kardex_lote = $cantidad_lote_ingresada;
                    } else {
                        $id_lote_existente = (int) $item['id_lote_existente'];
                        $resultado_existente = RecepcionData::registrar_recepcion_lote_existente(
                            $id_lote_existente,
                            $cantidad_base_ingresada
                        );
                        if (!$resultado_existente || !$resultado_existente['lote']) {
                            throw new \Exception('No se encontró el lote especificado para ajustar el stock');
                        }
                        $id_lote_producto = $id_lote_existente;
                        $cantidad_kardex_lote = $resultado_existente['cantidad_lote_ingresada'];
                    }

                    RecepcionData::registrar_kardex_recepcion(
                        $id_lote_producto,
                        $id_reabastecimiento_entrega,
                        $cantidad_kardex_lote,
                        $cantidad_base_ingresada,
                        $descripcion_kardex
                    );

                    // Atribución de cantidad recibida a los detalles de entrega de la DB (Greedy)
                    $por_atribuir = $cantidad_base_ingresada;
                    foreach ($db_detalles as $db_d) {
                        if ($db_d->estado_entrega_detalle === EstadoDetalleEntrega::Recibido->value) continue;
                        
                        $disponible = (float) $db_d->cantidad_base; // Aquí podrías llevar un track de "recibido_parcial" si quisieras
                        // Pero como el front agrupa TODO lo entregado, marcamos como recibido si consumimos algo o si ya está saldado.
                        // Para simplificar: si hay algo pendiente en este detalle, lo marcamos como recibido.
                        
                        EntregasData::marcar_entrega_detalle_como_recibido($db_d->id_entrega_detalle);
                        
                        // Si quieres ser estricto con las cantidades parciales, esto se complica. 
                        // El requerimiento dice que agrupemos lo entregado.
                    }
                }

                EntregasData::verificar_y_completar_entrega($id_reabastecimiento_entrega);
            }

            DB::commit();
            return ApiResponse::success(null, 'Recepciones globales registradas correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Ocurrió un error al procesar las recepciones: ' . $e->getMessage());
        }
    }
}
