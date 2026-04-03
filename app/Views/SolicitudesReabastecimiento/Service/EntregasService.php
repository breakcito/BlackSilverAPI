<?php

namespace App\Views\SolicitudesReabastecimiento\Service;

use App\Data\KardexProductosData;
use App\Data\LotesProductosData;
use App\Models\SolicitudReabastecimiento;
use App\Models\SolicitudReabastecimientoEntrega;
use App\Models\SolicitudReabastecimientoEntregaDetalle;
use App\Models\SolicitudReabastecimientoRecepcion;
use App\Models\SolicitudReabastecimientoRecepcionDetalle;
use App\Models\PrestamoAlmacen;
use App\Models\PrestamoAlmacenEntrega;
use App\Models\PrestamoAlmacenReposicion;
use App\Shared\Enums\Kardex\OrigenMovimiento;
use App\Shared\Enums\Kardex\TipoMovimiento;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoDetalleEntrega;
use App\Shared\Responses\ApiResponse;
use App\Views\SolicitudesReabastecimiento\Data\EntregasData;
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
        ], 0, $evidencias ?: []);
    }

    public static function recibir_entregas_bulk(array $recepciones, int $idEmpleado = 0, array $evidenciasRaiz = [])
    {
        try {
            DB::beginTransaction();

            foreach ($recepciones as $recepcion) {
                $id = (int) $recepcion['id_reabastecimiento_entrega'];
                $tipo = $recepcion['tipo_entrega'] ?? 'Solicitud';
                $items = $recepcion['items'];

                $info = self::_obtener_info_entrega($id, $tipo);
                $id_recepcion_header = self::_registrar_header_recepcion($id, $tipo, $recepcion, $idEmpleado, $evidenciasRaiz);
                $es_parcial = false;

                foreach ($items as $item) {
                    $id_det = (int) $item['id_solicitud_reabastecimiento_detalle'];
                    if (!$info['grouped']->has($id_det)) {
                        throw new \Exception('Uno de los detalles de entrega no existe');
                    }

                    $db_detalles = $info['grouped']->get($id_det);
                    $detalleBase = $db_detalles->first();
                    $cantidad_base_ingresada = (float) $item['cantidad_base'];
                    $id_producto = (int) $detalleBase->id_producto;
                    $desc_kardex = "Recepción de la entrega " . $info['correlativo_entrega'] . " por " . ($tipo === 'Prestamo' ? 'préstamo' : 'solicitud') . " " . $info['correlativo_solicitud'];
                    $es_nuevo_lote = (bool) $item['es_nuevo_lote'];

                    $id_lote_producto = null;
                    $cantidad_kardex_lote = 0;

                    $correlativo_data = LotesProductosData::get_nuevo_correlativo((int) $info['id_almacen']);
                    if ($es_nuevo_lote) {
                        $id_lote_producto = LotesProductosData::crear_lote(
                            id_producto: $id_producto,
                            id_unidad_medida: !empty($item['id_unidad_medida']) ? (int)$item['id_unidad_medida'] : (int) $detalleBase->id_unidad_medida_solicitada,
                            id_almacen: (int) $info['id_almacen'],
                            descripcion: !empty($item['descripcion']) ? $item['descripcion'] : null,
                            correlativo: $correlativo_data['correlativo'],
                            numero_correlativo: $correlativo_data['numero_correlativo'],
                            stock_inicial: $cantidad_base_ingresada,
                            contenido_por_presentacion: !empty($item['contenido_por_presentacion']) ? (float)$item['contenido_por_presentacion'] : (float) $detalleBase->contenido_por_presentacion_solicitado,
                            stock_actual_base: $cantidad_base_ingresada,
                            fecha_hora_ingreso: !empty($item['fecha_ingreso']) ? date('Y-m-d H:i:s', strtotime($item['fecha_ingreso'])) : null,
                            fecha_vencimiento: !empty($item['fecha_vencimiento']) ? date('Y-m-d', strtotime($item['fecha_vencimiento'])) : null,
                        );
                        $loteTemp = \App\Models\LoteProducto::find($id_lote_producto);
                        $cantidad_kardex_lote = $loteTemp ? $loteTemp->stock_actual : 0;
                    } else {
                        $lote_actual = LotesProductosData::get_lote_simple_by_id((int) $item['id_lote_existente']);
                        $nuevo_stock = $lote_actual['stock_actual'] + $cantidad_base_ingresada;
                        $nuevo_stock_base = $lote_actual['stock_actual_base'] + $cantidad_base_ingresada;
                        $resultado_existente = LotesProductosData::update_stock((int) $item['id_lote_existente'], $nuevo_stock, $nuevo_stock_base);

                        if (!$resultado_existente) throw new \Exception('Error al ajustar lote existente');
                        $id_lote_producto = (int) $item['id_lote_existente'];
                        $cantidad_kardex_lote = $resultado_existente['cantidad_lote_ingresada'];
                    }

                    KardexProductosData::registrar_kardex(
                        id_lote: $id_lote_producto,
                        id_origen: $id,
                        tipo_movimiento: TipoMovimiento::Ingreso,
                        tipo_origen: OrigenMovimiento::Recepcion,
                        descripcion: $desc_kardex,
                        cantidad_movimiento: $cantidad_kardex_lote,
                        cantidad_movimiento_base: $cantidad_base_ingresada,
                        nuevo_stock: $loteTemp->stock_actual,
                        nuevo_stock_base: $loteTemp->stock_actual_base,
                    );

                    $restante = $cantidad_base_ingresada;
                    foreach ($db_detalles as $db_d) {
                        if ($restante <= 0.0001) break;
                        if ($db_d->estado_entrega_detalle === EstadoDetalleEntrega::Recibido->value) continue;

                        $cant_shipped = (float) $db_d->cantidad_base;
                        $amount = min($restante, $cant_shipped);
                        $restante -= $amount;

                        if ($id_recepcion_header) {
                            if ($tipo === 'Prestamo') {
                                \App\Models\PrestamoAlmacenRecepcionDetalle::create([
                                    'id_prestamo_almacen_recepcion' => $id_recepcion_header,
                                    'id_prestamo_almacen_entrega_detalle' => $db_d->id_entrega_detalle,
                                    'cantidad_recepcionada_base' => $amount,
                                    'estado' => $amount < $cant_shipped ? 'Recepcionado Parcialmente' : 'Recepcionado',
                                ]);
                            } else {
                                SolicitudReabastecimientoRecepcionDetalle::create([
                                    'id_solicitud_reabastecimiento_recepcion' => $id_recepcion_header,
                                    'id_solicitud_reabastecimiento_entrega_detalle' => $db_d->id_entrega_detalle,
                                    'cantidad_recepcionada_base' => $amount,
                                    'estado' => $amount < $cant_shipped ? 'Recepcionado Parcialmente' : 'Recepcionado',
                                ]);
                            }
                        }

                        $ya_recibido = (float) ($db_d->cantidad_recibida_total ?? 0);
                        $total_acumulado = $ya_recibido + $amount;

                        if ($tipo === 'Solicitud') {
                            $nuevo_estado = ($total_acumulado >= $cant_shipped - 0.0001)
                                ? EstadoDetalleEntrega::Recibido->value
                                : 'Recibido Parcialmente';
                            if ($nuevo_estado === 'Recibido Parcialmente') $es_parcial = true;
                            SolicitudReabastecimientoEntregaDetalle::where('id', $db_d->id_entrega_detalle)->update(['estado' => $nuevo_estado]);
                        } else if ($tipo === 'Prestamo') {
                            $nuevo_estado = ($total_acumulado >= $cant_shipped - 0.0001)
                                ? \App\Shared\Enums\PrestamoAlmacen\EstadoEntregaPrestamo::Confirmada->value
                                : 'Recibido Parcialmente';
                            if ($nuevo_estado === 'Recibido Parcialmente') $es_parcial = true;
                            \App\Models\PrestamoAlmacenEntregaDetalle::where('id', $db_d->id_entrega_detalle)->update(['estado' => $nuevo_estado]);
                        } else if ($tipo === 'Reposicion') {
                            ReposicionesData::marcar_como_recibido($db_d->id_entrega_detalle);
                        }
                    }
                }

                if ($id_recepcion_header && $es_parcial) {
                    if ($tipo === 'Prestamo') {
                        \App\Models\PrestamoAlmacenRecepcion::where('id', $id_recepcion_header)->update(['estado' => 'Recepcionado Parcialmente']);
                    } else {
                        SolicitudReabastecimientoRecepcion::where('id', $id_recepcion_header)->update(['estado' => 'Recepcionado Parcialmente']);
                    }
                }

                self::_verificar_completar($id, $tipo);
            }

            DB::commit();
            return ApiResponse::success(null, 'Recepciones procesadas correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al procesar: ' . $e->getMessage());
        }
    }

    private static function _obtener_info_entrega(int $id, string $tipo): array
    {
        if ($tipo === 'Prestamo') {
            $entrega = PrestamoAlmacenEntrega::findOrFail($id);
            $prestamo = PrestamoAlmacen::find($entrega->id_prestamo_almacen);
            $solVinc = SolicitudReabastecimiento::find($prestamo->id_solicitud_reabastecimiento);
            return [
                'correlativo_entrega' => $entrega->correlativo,
                'correlativo_solicitud' => $prestamo->correlativo,
                'id_almacen' => $solVinc ? $solVinc->id_almacen_solicitante : 0,
                'grouped' => collect(EntregasDetalleData::get_detalles_entrega($id))->groupBy('id_solicitud_reabastecimiento_detalle'),
            ];
        }

        if ($tipo === 'Reposicion') {
            $repo = PrestamoAlmacenReposicion::findOrFail($id);
            $prestamo = PrestamoAlmacen::find($repo->id_prestamo_almacen);
            return [
                'correlativo_entrega' => $repo->correlativo,
                'correlativo_solicitud' => $prestamo ? $prestamo->correlativo : '',
                'id_almacen' => $prestamo ? $prestamo->id_almacen_prestamista : 0,
                'grouped' => collect(ReposicionesData::get_detalles_entrega_reposicion($id))->groupBy('id_solicitud_reabastecimiento_detalle'),
            ];
        }

        $entrega = SolicitudReabastecimientoEntrega::findOrFail($id);
        $solicitud = SolicitudReabastecimiento::findOrFail($entrega->id_solicitud_reabastecimiento);
        return [
            'correlativo_entrega' => $entrega->correlativo,
            'correlativo_solicitud' => $solicitud->correlativo,
            'id_almacen' => $solicitud->id_almacen_solicitante,
            'grouped' => collect(EntregasData::get_detalles_entrega($id))->groupBy('id_solicitud_reabastecimiento_detalle'),
        ];
    }

    private static function _registrar_header_recepcion(int $id, string $tipo, array $data, int $idEmpleado = 0, array $evidenciasRaiz = []): ?int
    {
        if (!in_array($tipo, ['Solicitud', 'Prestamo'])) return null;

        $evidenciasData = null;
        if (!empty($evidenciasRaiz)) {
            $path = $tipo === 'Prestamo' ? 'prestamos/recepciones' : 'reabastecimiento/recepciones';
            $evidenciasData = \App\Shared\Helpers\ArchivoHelper::guardarArchivos($path, $evidenciasRaiz);
        }

        $empleado_id = $idEmpleado > 0 ? $idEmpleado : (session('id_empleado') ?? 1);
        $observacion = $data['observacion'] ?? null;
        $fecha_hora = isset($data['fecha_hora_recepcion']) ? date('Y-m-d H:i:s', strtotime($data['fecha_hora_recepcion'])) : date('Y-m-d H:i:s');
        $con_incidencia = filter_var($data['con_incidencia'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

        if ($tipo === 'Prestamo') {
            $model = \App\Models\PrestamoAlmacenRecepcion::create([
                'id_prestamo_almacen_entrega' => $id,
                'id_empleado_registro' => $empleado_id,
                'observacion' => $observacion,
                'fecha_hora_recepcion' => $fecha_hora,
                'evidencias' => $evidenciasData ?? null,
                'con_incidencia' => $con_incidencia,
                'created_at' => date('Y-m-d H:i:s'),
                'estado' => 'Recepcionado',
            ]);
        } else {
            $model = SolicitudReabastecimientoRecepcion::create([
                'id_solicitud_reabastecimiento_entrega' => $id,
                'id_empleado_registro' => $empleado_id,
                'observacion' => $observacion,
                'fecha_hora_recepcion' => $fecha_hora,
                'evidencias' => $evidenciasData ?? null,
                'con_incidencia' => $con_incidencia,
                'created_at' => date('Y-m-d H:i:s'),
                'estado' => 'Recepcionado',
            ]);
        }
        return (int) $model->id;
    }

    private static function _verificar_completar(int $id, string $tipo): void
    {
        if ($tipo === 'Prestamo') {
            EntregasDetalleData::verificar_y_completar_entrega($id);
        } elseif ($tipo === 'Reposicion') {
            ReposicionesData::verificar_y_completar_reposicion($id);
        } else {
            EntregasData::verificar_y_completar_entrega($id);
        }
    }

    public static function get_historial_recepciones_entrega(int $id_entrega, string $tipo = 'Solicitud')
    {
        if ($tipo === 'Prestamo') {
            $sql = "
                SELECT r.*, CONCAT(e.nombre, ' ', e.apellido) as empleado_registro
                FROM prestamo_almacen_recepcion r
                JOIN empleado e ON e.id = r.id_empleado_registro
                WHERE r.id_prestamo_almacen_entrega = :id_entrega
                ORDER BY r.created_at DESC
            ";
            $recepciones = DB::select($sql, ['id_entrega' => $id_entrega]);

            foreach ($recepciones as $recepcion) {
                $decoded = $recepcion->evidencias ? json_decode($recepcion->evidencias, true) : null;
                if (is_string($decoded)) $decoded = json_decode($decoded, true);
                $recepcion->evidencias = is_array($decoded) ? $decoded : null;

                $recepcion->detalles = DB::select("
                    SELECT rd.*, p.nombre as producto, u.abreviatura as unidad
                    FROM prestamo_almacen_recepcion_detalle rd
                    JOIN prestamo_almacen_entrega_detalle ed ON ed.id = rd.id_prestamo_almacen_entrega_detalle
                    JOIN prestamo_almacen_detalle pd ON pd.id = ed.id_prestamo_almacen_detalle
                    JOIN solicitud_reabastecimiento_detalle sd ON sd.id = pd.id_solicitud_reabastecimiento_detalle
                    JOIN producto p ON p.id = sd.id_producto
                    JOIN unidad_medida u ON u.id = p.id_unidad_medida_base
                    WHERE rd.id_prestamo_almacen_recepcion = :id_recepcion
                ", ['id_recepcion' => $recepcion->id]);
            }
        } else {
            $sql = "
                SELECT r.*, CONCAT(e.nombre, ' ', e.apellido) as empleado_registro
                FROM solicitud_reabastecimiento_recepcion r
                JOIN empleado e ON e.id = r.id_empleado_registro
                WHERE r.id_solicitud_reabastecimiento_entrega = :id_entrega
                ORDER BY r.created_at DESC
            ";

            $recepciones = DB::select($sql, ['id_entrega' => $id_entrega]);

            foreach ($recepciones as $recepcion) {
                $decoded = $recepcion->evidencias ? json_decode($recepcion->evidencias, true) : null;
                if (is_string($decoded)) $decoded = json_decode($decoded, true);
                $recepcion->evidencias = is_array($decoded) ? $decoded : null;

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
        }

        return ApiResponse::success($recepciones);
    }
}
