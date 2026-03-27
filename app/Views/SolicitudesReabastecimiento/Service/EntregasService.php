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
    public static function recibir_entregas(int $id_reabastecimiento_entrega, array $items, string $tipo_entrega = 'Solicitud')
    {
        return self::recibir_entregas_bulk([
            [
                'id_reabastecimiento_entrega' => $id_reabastecimiento_entrega,
                'tipo_entrega' => $tipo_entrega,
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
                $tipo_entrega = $recepcion['tipo_entrega'] ?? 'Solicitud';
                $items = $recepcion['items'];

                $correlativo_entrega = "";
                $correlativo_solicitud = "";
                $id_almacen = 0;
                $detalles_grouped = null;

                if ($tipo_entrega === 'Prestamo') {
                    $entrega = \App\Models\PrestamoAlmacenEntrega::find($id_reabastecimiento_entrega);
                    if (!$entrega) {
                        throw new \Exception("La entrega de préstamo ID {$id_reabastecimiento_entrega} no existe");
                    }
                    $prestamo = \App\Models\PrestamoAlmacen::find($entrega->id_prestamo_almacen);
                    $solicitud_vinc = \App\Models\SolicitudReabastecimiento::find($prestamo->id_solicitud_reabastecimiento);

                    $correlativo_entrega = $entrega->correlativo;
                    // Usar el correlativo del PRÉSTAMO siempre que sea un préstamo, no el de la solicitud vinculada
                    $correlativo_solicitud = $prestamo->correlativo; 
                    $id_almacen = $solicitud_vinc ? $solicitud_vinc->id_almacen_solicitante : 0; 

                    $detalles_entrega = \App\Views\PrestamosAlmacenAtencion\Data\EntregasDetalleData::get_detalles_entrega($id_reabastecimiento_entrega);
                    $detalles_grouped = collect($detalles_entrega)->groupBy('id_solicitud_reabastecimiento_detalle');
                } else {
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

                    $detalles_entrega = EntregasData::get_detalles_entrega($id_reabastecimiento_entrega);
                    $detalles_grouped = collect($detalles_entrega)->groupBy('id_solicitud_reabastecimiento_detalle');
                }

                foreach ($items as $item) {
                    $id_solicitud_detalle = (int) $item['id_solicitud_reabastecimiento_detalle'];
                    $es_nuevo_lote = (bool) $item['es_nuevo_lote'];

                    if (!$detalles_grouped->has($id_solicitud_detalle)) {
                        throw new \Exception('Uno de los detalles de entrega no existe');
                    }

                    $db_detalles = $detalles_grouped->get($id_solicitud_detalle);
                    $detalleBase = $db_detalles->first();

                    $cantidad_base_ingresada = (float) $item['cantidad_base'];
                    $id_producto = (int) $detalleBase->id_producto;
                    $descripcion_kardex = "Recepción de la entrega " . $correlativo_entrega . " por " . ($tipo_entrega === 'Prestamo' ? 'préstamo' : 'solicitud') . " " . $correlativo_solicitud;

                    $id_lote_producto = null;
                    $cantidad_kardex_lote = 0;

                    if ($es_nuevo_lote) {
                        $fecha_vencimiento = !empty($item['fecha_vencimiento']) ? date('Y-m-d', strtotime($item['fecha_vencimiento'])) : null;
                        $fecha_ingreso = !empty($item['fecha_ingreso']) ? date('Y-m-d H:i:s', strtotime($item['fecha_ingreso'])) : null;
                        $descripcion_default = "Lote generado por recepción de entrega " . $correlativo_entrega . " de " . ($tipo_entrega === 'Prestamo' ? 'préstamo' : 'solicitud') . " " . $correlativo_solicitud;
                        $descripcion_lote = !empty($item['descripcion']) ? $item['descripcion'] : $descripcion_default;
                        
                        $id_unidad_medida_solicitada = !empty($item['id_unidad_medida']) ? (int)$item['id_unidad_medida'] : (int) $detalleBase->id_unidad_medida_solicitada;
                        $contenido_solicitado = !empty($item['contenido_por_presentacion']) ? (float)$item['contenido_por_presentacion'] : (float) $detalleBase->contenido_por_presentacion_solicitado;

                        $cantidad_lote_ingresada = $cantidad_base_ingresada / $contenido_solicitado;

                        if (empty($id_almacen)) {
                            throw new \Exception("No se pudo determinar el almacén de destino para la entrega " . $correlativo_entrega);
                        }

                        $id_lote_producto = RecepcionData::registrar_recepcion_lote_nuevo(
                            $id_producto,
                            $id_unidad_medida_solicitada,
                            (int) $id_almacen,
                            $fecha_vencimiento,
                            $cantidad_lote_ingresada,
                            $cantidad_base_ingresada,
                            $contenido_solicitado,
                            $descripcion_lote,
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

                    foreach ($db_detalles as $db_d) {
                        if ($tipo_entrega === 'Prestamo') {
                            if ($db_d->estado_entrega_detalle === \App\Shared\Enums\PrestamoAlmacen\EstadoEntregaPrestamo::Confirmada->value) continue;
                            \App\Views\PrestamosAlmacenAtencion\Data\EntregasDetalleData::marcar_como_recibido($db_d->id_entrega_detalle, $id_lote_producto);
                        } else {
                            if ($db_d->estado_entrega_detalle === EstadoDetalleEntrega::Recibido->value) continue;
                            EntregasData::marcar_entrega_detalle_como_recibido($db_d->id_entrega_detalle);
                        }
                    }
                }

                if ($tipo_entrega === 'Prestamo') {
                    \App\Views\PrestamosAlmacenAtencion\Data\EntregasDetalleData::verificar_y_completar_entrega($id_reabastecimiento_entrega);
                } else {
                    EntregasData::verificar_y_completar_entrega($id_reabastecimiento_entrega);
                }
            }

            DB::commit();
            return ApiResponse::success(null, 'Recepciones globales registradas correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Ocurrió un error al procesar las recepciones: ' . $e->getMessage());
        }
    }
}
