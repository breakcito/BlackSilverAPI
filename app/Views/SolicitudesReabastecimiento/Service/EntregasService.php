<?php

namespace App\Views\SolicitudesReabastecimiento\Service;

use App\Models\SolicitudReabastecimiento;
use App\Models\SolicitudReabastecimientoEntrega;
use App\Models\SolicitudReabastecimientoEntregaDetalle;
use App\Models\SolicitudReabastecimientoRecepcion;
use App\Models\SolicitudReabastecimientoRecepcionDetalle;
use App\Models\PrestamoAlmacen;
use App\Models\PrestamoAlmacenEntrega;
use App\Models\PrestamoAlmacenReposicion;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoDetalleEntrega;
use App\Shared\Responses\ApiResponse;
use App\Views\SolicitudesReabastecimiento\Data\EntregasData;
use App\Views\SolicitudesReabastecimiento\Data\RecepcionData;
use App\Views\PrestamosAlmacenAtencion\Data\EntregasDetalleData;
use App\Views\PrestamosAlmacen\Data\ReposicionesData;
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
    public static function recibir_entregas(
        int $id_reabastecimiento_entrega,
        array $items,
        string $tipo_entrega = 'Solicitud',
        bool $con_incidencia = false,
        ?string $observacion = null,
        ?array $evidencias = null,
        ?string $fecha_hora_recepcion = null
    ) {
        return self::recibir_entregas_bulk([
            [
                'id_reabastecimiento_entrega' => $id_reabastecimiento_entrega,
                'tipo_entrega' => $tipo_entrega,
                'items' => $items,
                'con_incidencia' => $con_incidencia,
                'observacion' => $observacion,
                'evidencias' => $evidencias,
                'fecha_hora_recepcion' => $fecha_hora_recepcion
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
                    $entrega = PrestamoAlmacenEntrega::find($id_reabastecimiento_entrega);
                    if (!$entrega) {
                        throw new \Exception("La entrega de préstamo ID {$id_reabastecimiento_entrega} no existe");
                    }
                    $prestamo = PrestamoAlmacen::find($entrega->id_prestamo_almacen);
                    $solicitud_vinc = SolicitudReabastecimiento::find($prestamo->id_solicitud_reabastecimiento);

                    $correlativo_entrega = $entrega->correlativo;
                    $correlativo_solicitud = $prestamo->correlativo;
                    $id_almacen = $solicitud_vinc ? $solicitud_vinc->id_almacen_solicitante : 0;

                    $detalles_entrega = EntregasDetalleData::get_detalles_entrega($id_reabastecimiento_entrega);
                    $detalles_grouped = collect($detalles_entrega)->groupBy('id_solicitud_reabastecimiento_detalle');
                } else if ($tipo_entrega === 'Reposicion') {
                    $reposicion = PrestamoAlmacenReposicion::find($id_reabastecimiento_entrega);
                    if (!$reposicion) {
                        throw new \Exception("La reposición ID {$id_reabastecimiento_entrega} no existe");
                    }
                    $prestamo = PrestamoAlmacen::find($reposicion->id_prestamo_almacen);
                    $id_almacen = $prestamo ? $prestamo->id_almacen_prestamista : 0;

                    $correlativo_entrega = $reposicion->correlativo;
                    $correlativo_solicitud = $prestamo ? $prestamo->correlativo : "";

                    $detalles_entrega = ReposicionesData::get_detalles_entrega_reposicion($id_reabastecimiento_entrega);
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

                // --- NUEVA LÓGICA DE TRAZABILIDAD (SOLO PARA SOLICITUDES) ---
                $id_recepcion_header = null;
                if ($tipo_entrega === 'Solicitud') {
                    /** @var SolicitudReabastecimientoRecepcion $recepcionModel */
                    $recepcionModel = SolicitudReabastecimientoRecepcion::create([
                        'id_solicitud_reabastecimiento_entrega' => $id_reabastecimiento_entrega,
                        'id_empleado_registro' => auth()->id(),
                        'observacion' => $recepcion['observacion'] ?? null,
                        'fecha_hora_recepcion' => $recepcion['fecha_hora_recepcion'] ?? now(),
                        'evidencias' => $recepcion['evidencias'] ?? [],
                        'con_incidencia' => $recepcion['con_incidencia'] ?? false,
                        'created_at' => now(),
                        'estado' => 'Recepcionado', 
                    ]);
                    $id_recepcion_header = (int) $recepcionModel->id;
                }

                $es_recepcion_parcial_header = false;

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
                        $id_lote_producto = RecepcionData::registrar_recepcion_lote_nuevo(
                            $id_producto,
                            !empty($item['id_unidad_medida']) ? (int)$item['id_unidad_medida'] : (int) $detalleBase->id_unidad_medida_solicitada,
                            (int) $id_almacen,
                            !empty($item['fecha_vencimiento']) ? date('Y-m-d', strtotime($item['fecha_vencimiento'])) : null,
                            $cantidad_base_ingresada / (!empty($item['contenido_por_presentacion']) ? (float)$item['contenido_por_presentacion'] : (float) $detalleBase->contenido_por_presentacion_solicitado),
                            $cantidad_base_ingresada,
                            !empty($item['contenido_por_presentacion']) ? (float)$item['contenido_por_presentacion'] : (float) $detalleBase->contenido_por_presentacion_solicitado,
                            !empty($item['descripcion']) ? $item['descripcion'] : null,
                            !empty($item['fecha_ingreso']) ? date('Y-m-d H:i:s', strtotime($item['fecha_ingreso'])) : null
                        );
                        $loteTemp = \App\Models\LoteProducto::find($id_lote_producto);
                        $cantidad_kardex_lote = $loteTemp ? $loteTemp->stock_actual : 0;
                    } else {
                        $resultado_existente = RecepcionData::registrar_recepcion_lote_existente((int) $item['id_lote_existente'], $cantidad_base_ingresada);
                        if (!$resultado_existente) throw new \Exception('Error al ajustar lote existente');
                        $id_lote_producto = (int) $item['id_lote_existente'];
                        $cantidad_kardex_lote = $resultado_existente['cantidad_lote_ingresada'];
                    }

                    RecepcionData::registrar_kardex_recepcion($id_lote_producto, $id_reabastecimiento_entrega, $cantidad_kardex_lote, $cantidad_base_ingresada, $descripcion_kardex);

                    $restante_por_distribuir = $cantidad_base_ingresada;
                    foreach ($db_detalles as $db_d) {
                        if ($restante_por_distribuir <= 0.0001) break;
                        if ($db_d->estado_entrega_detalle === EstadoDetalleEntrega::Recibido->value) continue;

                        $cant_detalle_shipped = (float) $db_d->cantidad_base;
                        // TODO: Restar lo ya recepcionado si permitimos múltiples eventos
                        $amount_to_receive = min($restante_por_distribuir, $cant_detalle_shipped);
                        $restante_por_distribuir -= $amount_to_receive;

                        if ($id_recepcion_header) {
                            SolicitudReabastecimientoRecepcionDetalle::create([
                                'id_solicitud_reabastecimiento_recepcion' => $id_recepcion_header,
                                'id_solicitud_reabastecimiento_entrega_detalle' => $db_d->id_entrega_detalle,
                                'cantidad_recepcionada_base' => $amount_to_receive,
                                'estado' => $amount_to_receive < $cant_detalle_shipped ? 'Recepcionado Parcialmente' : 'Recepcionado',
                            ]);
                        }

                        if ($tipo_entrega === 'Solicitud') {
                            $nuevo_estado = ($amount_to_receive < $cant_detalle_shipped) ? 'Recibido Parcialmente' : EstadoDetalleEntrega::Recibido->value;
                            if ($nuevo_estado === 'Recibido Parcialmente') $es_recepcion_parcial_header = true;
                            SolicitudReabastecimientoEntregaDetalle::where('id', $db_d->id_entrega_detalle)->update(['estado' => $nuevo_estado]);
                        } else if ($tipo_entrega === 'Prestamo') {
                            EntregasDetalleData::marcar_como_recibido($db_d->id_entrega_detalle);
                        } else if ($tipo_entrega === 'Reposicion') {
                            ReposicionesData::marcar_como_recibido($db_d->id_entrega_detalle);
                        }
                    }
                }

                if ($id_recepcion_header && $es_recepcion_parcial_header) {
                    SolicitudReabastecimientoRecepcion::where('id', $id_recepcion_header)->update(['estado' => 'Recepcionado Parcialmente']);
                }

                if ($tipo_entrega === 'Prestamo') {
                    EntregasDetalleData::verificar_y_completar_entrega($id_reabastecimiento_entrega);
                } else if ($tipo_entrega === 'Reposicion') {
                    ReposicionesData::verificar_y_completar_reposicion($id_reabastecimiento_entrega);
                } else {
                    EntregasData::verificar_y_completar_entrega($id_reabastecimiento_entrega);
                }
            }

            DB::commit();
            return ApiResponse::success(null, 'Recepciones procesadas correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al procesar: ' . $e->getMessage());
        }
    }

    public static function get_historial_recepciones_entrega(int $id_entrega)
    {
        $sql = "
            SELECT r.*, CONCAT(e.nombre, ' ', e.apellido) as empleado_registro
            FROM solicitud_reabastecimiento_recer r
            JOIN empleado e ON e.id = r.id_empleado_registro
            WHERE r.id_solicitud_reabastecimiento_entrega = :id_entrega
            ORDER BY r.created_at DESC
        ";
        // Correction: table name was typoed in my thought
        $sql = str_replace('recer', 'recepcion', $sql);

        $recepciones = DB::select($sql, ['id_entrega' => $id_entrega]);

        foreach ($recepciones as $recepcion) {
            $recepcion->evidencias = $recepcion->evidencias ? json_decode($recepcion->evidencias) : [];
            $recepcion->detalles = DB::select("
                SELECT rd.*, p.nombre as producto, u.abreviatura as unidad
                FROM solicitud_reabastecimiento_recepcion_detalle rd
                JOIN solicitud_reabastecimiento_entrega_detalle ed ON ed.id = rd.id_solicitud_reabastecimiento_entrega_detalle
                JOIN solicitud_reabastecimiento_detalle sd ON sd.id = ed.id_solicitud_reabastecimiento_detalle
                JOIN producto p ON p.id = sd.id_producto
                JOIN unidad_medida u ON u.id = p.id_unidad_medida_base
                WHERE rd.id_solicitud_reabastecimiento_recepcion = :id_recepcion
            ", ['id_recepcion' => $recepcion->id]);
        }

        return ApiResponse::success($recepciones);
    }
}
