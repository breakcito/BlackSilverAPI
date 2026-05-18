<?php

namespace App\Modules\PrestamosAlmacenAtencion\Service;

use App\Data\LotesProductosData;
use App\Services\ActivosFijosService;
use App\Services\LotesProductosService;
use App\Shared\Enums\ActivoFijo\MovimientoActivoFijo;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use App\Shared\Enums\PrestamoAlmacen\EstadoPrestamoDetalle;
use App\Shared\Enums\PrestamoAlmacen\EstadoPrestamo;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalleLog;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use App\Modules\PrestamosAlmacenAtencion\Data\EntregasData;
use App\Modules\PrestamosAlmacenAtencion\Data\EntregasDetalleData;
use App\Modules\PrestamosAlmacenAtencion\Data\PrestamosData;
use App\Modules\PrestamosAlmacenAtencion\Data\PrestamosDetalleData;
use App\Shared\Enums\PrestamoAlmacen\EstadoPrestamoDetalleLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EntregaService
{
    /**
     * Registra un despacho de préstamo con transaccionalidad.
     */
    public static function registrar_despacho(
        int $id_prestamo,
        int $id_empleado_entrega,
        int $id_personal_recibe,
        string $fecha_hora_entrega,
        ?string $observacion,
        ?array $evidencias, // Archivos
        array $detalles
    ) {
        return DB::transaction(function () use ($id_prestamo, $id_empleado_entrega, $id_personal_recibe, $fecha_hora_entrega, $observacion, $evidencias, $detalles) {
            $fecha_mysql = ($fecha_hora_entrega && $fecha_hora_entrega !== "null")
                ? Carbon::parse($fecha_hora_entrega)->toDateTimeString()
                : now()->toDateTimeString();

            // 0. Pre-cargar lotes solo para ítems sin activo fijo
            $items_con_lote = array_filter($detalles, fn($d) => empty($d['id_activo_fijo']));
            $ids_lotes = array_map(fn($d) => (int) $d['id_lote_producto'], $items_con_lote);

            $lotesMap = !empty($ids_lotes)
                ? collect(LotesProductosData::get_lote_simple_by_id($ids_lotes))->keyBy('id_lote')
                : collect();

            // Validar Stock solo para productos comunes
            foreach ($items_con_lote as $det) {
                $id_lote = (int) $det['id_lote_producto'];
                $lote = $lotesMap->get($id_lote);
                if (!$lote || $lote['stock_actual_base'] < (float) $det['cantidad_base']) {
                    return ApiResponse::error("Stock insuficiente en el lote: " . ($lote['correlativo'] ?? 'ID: ' . $id_lote));
                }
            }

            // 1. Procesar Evidencias si existen
            $evidenciasData = null;
            if (!empty($evidencias)) {
                $evidenciasData = ArchivoHelper::guardarArchivos('prestamos_almacen_entregas', $evidencias);
            }

            // 2. Obtener cabecera del préstamo para el correlativo y datos del Kardex
            $prestamo = PrestamosData::get_prestamo_header_by_id($id_prestamo);
            if (!$prestamo) {
                return ApiResponse::error("El préstamo no existe");
            }

            // Generar Correlativo para Entrega (ENT) filtrado por el almacén que presta
            $correlativoData = EntregasData::get_nuevo_correlativo((int) $prestamo->id_almacen_prestamista);

            // 3. Crear Cabecera de Entrega de Préstamo
            $id_entrega = EntregasData::crear_entrega(
                $id_prestamo,
                $id_empleado_entrega,
                $id_personal_recibe,
                $correlativoData['correlativo'],
                $correlativoData['numero_correlativo'],
                $fecha_mysql,
                $observacion,
                $evidenciasData
            );

            $almSol = PrestamosData::get_almacen_solicitante_by_id($id_prestamo);
            $nombreAlmDestino = $almSol ? $almSol->nombre : 'Desconocido';
            $id_almacen_destino = $almSol ? (int) $almSol->id_almacen : null;

            // 4. Procesar Detalles y Afectar Stock/Kardex/Vínculos
            foreach ($detalles as $det) {
                $id_prestamo_detalle = (int) $det['id_prestamo_detalle'];
                $id_activo           = !empty($det['id_activo_fijo']) ? (int) $det['id_activo_fijo'] : null;
                $es_activo           = $id_activo !== null;

                if ($es_activo) {
                    // --- Camino: Activo Fijo ---
                    // La ubicacion de ese activo esta fuera de cualquier almacen
                    ActivosFijosService::new_ubicacion(
                        id_activo: $id_activo,
                        tipo_movimiento: MovimientoActivoFijo::DeAlmacenAAlmacen,
                        id_almacen: null,
                        id_mina: null,
                        descripcion: "Entrega (préstamo) N° {$correlativoData['correlativo']} al almacén {$nombreAlmDestino}",
                        fecha_hora_movimiento: $fecha_mysql
                    );

                    // Detalle sin lote
                    $id_det_entrega = EntregasDetalleData::crear_detalle_entrega(
                        $id_entrega,
                        $id_prestamo_detalle,
                        null,   // id_lote
                        1,      // cantidad
                        1,      // cantidad_base
                        $det['comentario'] ?? null,
                        $id_activo
                    );
                } else {
                    // --- Camino: Producto Común con Lote ---
                    $id_lote    = (int) $det['id_lote_producto'];
                    $cant_lote  = (float) $det['cantidad_lote'];
                    $cant_base  = (float) $det['cantidad_base'];

                    $id_det_entrega = EntregasDetalleData::crear_detalle_entrega(
                        $id_entrega,
                        $id_prestamo_detalle,
                        $id_lote,
                        $cant_lote,
                        $cant_base,
                        $det['comentario'] ?? null
                    );

                    LotesProductosService::update_stock(
                        id_lote: $id_lote,
                        id_origen: $id_det_entrega,
                        tabla_origen: 'prestamo_almacen_entrega_detalle',
                        tipo_origen: KardexOrigenMovimiento::Entrega,
                        tipo_movimiento: KardexTipoMovimiento::Salida,
                        cantidad_movimiento_base: $cant_base,
                        descripcion: "Entrega N° {$correlativoData['correlativo']} al almacén {$nombreAlmDestino}",
                    );
                }

                $cant_lote      = $es_activo ? 1 : (float) $det['cantidad_lote'];
                $cant_base      = $es_activo ? 1 : (float) $det['cantidad_base'];
                $cant_solicitud = $es_activo ? 1 : (float) $det['cantidad_solicitud'];

                // 4.5 Actualizar cantidad acumulada en el Préstamo
                EntregasData::registrar_incremento_cantidades_prestadas($id_prestamo_detalle, $cant_solicitud, $cant_base);

                // 4.5.1 Transición de estado del ítem a "Despacho iniciado"
                $updatedItem = DB::table('prestamo_almacen_detalle')
                    ->where('id', $id_prestamo_detalle)
                    ->where('estado', EstadoPrestamoDetalle::Aprobado->value)
                    ->update(['estado' => EstadoPrestamoDetalle::EnDespacho->value]);

                if ($updatedItem) {
                    PrestamosDetalleData::insert_detalle_log(
                        $id_prestamo_detalle,
                        $id_empleado_entrega,
                        EstadoPrestamoDetalle::EnDespacho->value,
                        EstadoPrestamoDetalle::EnDespacho->getGlosa()
                    );
                }

                // 4.6 IMPACTO EN REABASTECIMIENTO (PROGRESO)
                $vinc = EntregasData::get_ids_vinculados_by_prestamo_detalle($id_prestamo_detalle);
                if ($vinc && $vinc->id_solicitud_reabastecimiento_detalle) {
                    $id_sol_det = (int) $vinc->id_solicitud_reabastecimiento_detalle;
                    EntregasData::incrementar_entregado_reabastecimiento($id_sol_det, $cant_solicitud, $cant_base);

                    $reabastecimientoLogGlosa = EstadoSolicitudDetalleLog::NuevaEntrega->getGlosa((string) $cant_solicitud);
                    EntregasData::insertar_log_reabastecimiento(
                        $id_sol_det,
                        $id_empleado_entrega,
                        $reabastecimientoLogGlosa,
                        EstadoSolicitudDetalleLog::NuevaEntrega->value
                    );
                }

                // 4.7 LOG DE TRAZABILIDAD DEL PRÉSTAMO
                $prestamoLogGlosa = EstadoPrestamoDetalleLog::NuevaEntrega->getGlosa((string) $cant_lote);
                PrestamosDetalleData::insert_detalle_log(
                    $id_prestamo_detalle,
                    $id_empleado_entrega,
                    EstadoPrestamoDetalleLog::NuevaEntrega->value,
                    $prestamoLogGlosa
                );

                // 4.8 Verificar si se completó la cantidad para cerrar el ítem
                PrestamosDetalleData::verificar_y_cerrar_detalle($id_prestamo_detalle, $id_empleado_entrega);

                // 4.9 Asegurar que la cabecera del préstamo esté "En Proceso"
                DB::table('prestamo_almacen')
                    ->where('id', $id_prestamo)
                    ->where('estado', EstadoPrestamo::Generado->value)
                    ->update(['estado' => EstadoPrestamo::EnDespacho->value]);
            }

            return ApiResponse::success([
                'correlativo' => $correlativoData['correlativo'],
                'id_entrega' => $id_entrega
            ], "Despacho N° {$correlativoData['correlativo']} registrado exitosamente");
        });
    }

    /**
     * Obtiene el historial de entregas de todos los préstamos vinculados a una solicitud, con sus detalles.
     */
    public static function get_historial_por_solicitud(int $id_solicitud)
    {
        $data = EntregasData::get_entregas_por_solicitud((int) $id_solicitud);

        foreach ($data as $entrega) {
            $entrega->evidencias = $entrega->evidencias ? json_decode($entrega->evidencias) : null;
            $entrega->detalles = EntregasData::get_detalles_entrega((int) $entrega->id_entrega);
        }

        return ApiResponse::success($data);
    }

    public static function get_lotes_disponibles(int $id_almacen_solicitante, array $id_productos): array
    {
        return LotesProductosData::get_lotes_disponibles($id_almacen_solicitante, $id_productos);
    }
}
