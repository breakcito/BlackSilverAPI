<?php

namespace App\Views\PrestamosAlmacenAtencion\Service;

use App\Shared\Enums\PrestamoAlmacen\EstadoDetallePrestamo;
use App\Shared\Enums\PrestamoAlmacen\EstadoPrestamo;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalle;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use App\Views\PrestamosAlmacenAtencion\Data\AuxData;
use App\Views\PrestamosAlmacenAtencion\Data\EntregasData;
use App\Views\PrestamosAlmacenAtencion\Data\EntregasDetalleData;
use App\Views\PrestamosAlmacenAtencion\Data\PrestamosData;
use App\Views\PrestamosAlmacenAtencion\Data\PrestamosDetalleData;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class EntregaService
{
    /**
     * Registra un despacho de préstamo con transaccionalidad.
     */
    public static function registrar_despacho(
        int $id_prestamo,
        int $id_empleado_entrega,
        int $id_empleado_recibe,
        string $fecha_hora_entrega,
        ?string $observacion,
        ?array $evidencias, // Archivos
        array $detalles
    ) {
        return DB::transaction(function () use (
            $id_prestamo,
            $id_empleado_entrega,
            $id_empleado_recibe,
            $fecha_hora_entrega,
            $observacion,
            $evidencias,
            $detalles
        ) {
            $fecha_mysql = ($fecha_hora_entrega && $fecha_hora_entrega !== "null") 
                ? Carbon::parse($fecha_hora_entrega)->toDateTimeString()
                : now()->toDateTimeString();

            // 0. Procesar Evidencias si existen
            $evidenciasData = null;
            if (!empty($evidencias)) {
                $evidenciasData = ArchivoHelper::guardarArchivos('prestamos_almacen_entregas', $evidencias);
            }

            // 1. Validar Stock General antes de empezar
            foreach ($detalles as $det) {
                $id_lote = (int) $det['id_lote_salida'];
                $cant_base = (float) $det['cantidad_base'];

                $lote = AuxData::get_lote_by_id($id_lote);
                if (!$lote || $lote->stock_actual_base < $cant_base) {
                    return ApiResponse::error("Stock insuficiente en el lote: " . ($lote->correlativo ?? 'ID: ' . $id_lote));
                }
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
                $id_empleado_recibe,
                $correlativoData['correlativo'],
                $correlativoData['numero_correlativo'],
                $fecha_mysql,
                $observacion,
                $evidenciasData
            );

            // Información del almacén destino (Solicitante) para el Kardex
            $almSol = PrestamosData::get_almacen_solicitante_by_id($id_prestamo);
            $nombreAlmDestino = $almSol ? $almSol->nombre : 'Desconocido';

            // 4. Procesar Detalles y Afectar Stock/Kardex/Vínculos
            foreach ($detalles as $det) {
                $id_prestamo_detalle = (int) $det['id_prestamo_detalle'];
                $id_lote = (int) $det['id_lote_salida'];
                $cant_lote = (float) $det['cantidad_lote'];
                $cant_base = (float) $det['cantidad_base'];
                $cant_solicitud = (float) $det['cantidad_solicitud']; // Cantidad en la unidad de la solicitud

                // 4.1 Crear Detalle Entrega
                $id_det_entrega = EntregasDetalleData::crear_detalle_entrega(
                    $id_entrega,
                    $id_prestamo_detalle,
                    $id_lote,
                    $cant_lote
                );

                // 4.2 Cargar Lote para cálculos
                $lote = AuxData::get_lote_by_id($id_lote);
                $stock_anterior = $lote->stock_actual;
                $stock_anterior_base = $lote->stock_actual_base;
                $nuevo_stock = $stock_anterior - $cant_lote;
                $nuevo_stock_base = $stock_anterior_base - $cant_base;

                // 4.3 Actualizar Stock del Lote
                AuxData::update_lote_stock($id_lote, $nuevo_stock, $nuevo_stock_base);

                // 4.4 Registrar Kardex (Salida)
                AuxData::registrar_kardex(
                    $id_lote,
                    $id_det_entrega,
                    $stock_anterior,
                    $stock_anterior_base,
                    $cant_lote,
                    $cant_base,
                    $nuevo_stock,
                    $nuevo_stock_base,
                    "Salida por Préstamo al Almacén: {$nombreAlmDestino} - Entrega {$correlativoData['correlativo']}"
                );

                // 4.5 Actualizar cantidad acumulada en el Préstamo
                EntregasData::registrar_incremento_cantidades_prestadas($id_prestamo_detalle, $cant_solicitud, $cant_base);

                // 4.5.1 Transición de estado del ítem a "Despacho iniciado"
                $updatedItem = DB::table('prestamo_almacen_detalle')
                    ->where('id', $id_prestamo_detalle)
                    ->where('estado', EstadoDetallePrestamo::Aprobado->value)
                    ->update(['estado' => EstadoDetallePrestamo::DespachoIniciado->value]);

                if ($updatedItem) {
                    PrestamosDetalleData::insert_detalle_log(
                        $id_prestamo_detalle,
                        $id_empleado_entrega,
                        EstadoDetallePrestamo::DespachoIniciado->value,
                        EstadoDetallePrestamo::DespachoIniciado->getGlosa()
                    );
                }

                // 4.6 IMPACTO EN REABASTECIMIENTO (PROGRESO)
                $vinc = EntregasData::get_ids_vinculados_by_prestamo_detalle($id_prestamo_detalle);
                if ($vinc && $vinc->id_solicitud_reabastecimiento_detalle) {
                    $id_sol_det = (int) $vinc->id_solicitud_reabastecimiento_detalle;
                    
                    // Incrementamos la cantidad entregada en la solicitud de reabastecimiento
                    EntregasData::incrementar_entregado_reabastecimiento($id_sol_det, $cant_solicitud, $cant_base);

                    // LOG DE REABASTECIMIENTO (Original)
                    $reabastecimientoLogGlosa = EstadoSolicitudDetalle::NuevaEntrega->getGlosa((string)$cant_solicitud);
                    EntregasData::insertar_log_reabastecimiento(
                        $id_sol_det, 
                        $id_empleado_entrega, 
                        $reabastecimientoLogGlosa, 
                        EstadoSolicitudDetalle::NuevaEntrega->value
                    );
                }
                
                // 4.7 LOG DE TRAZABILIDAD DEL PRÉSTAMO
                $prestamoLogGlosa = EstadoDetallePrestamo::NuevaEntrega->getGlosa((string)$cant_lote);
                PrestamosDetalleData::insert_detalle_log(
                    $id_prestamo_detalle,
                    $id_empleado_entrega,
                    EstadoDetallePrestamo::NuevaEntrega->value,
                    $prestamoLogGlosa
                );

                // 4.8 Verificar si se completó la cantidad para cerrar el ítem
                PrestamosDetalleData::verificar_y_cerrar_detalle($id_prestamo_detalle, $id_empleado_entrega);

                // 4.9 Asegurar que la cabecera del préstamo esté "En Proceso"
                DB::table('prestamo_almacen')
                    ->where('id', $id_prestamo)
                    ->where('estado', EstadoPrestamo::Generado->value)
                    ->update(['estado' => EstadoPrestamo::EnProceso->value]);
            }

            return ApiResponse::success(
                $correlativoData['correlativo'],
                "Despacho N° {$correlativoData['correlativo']} registrado exitosamente"
            );
        });
    }
}
