<?php

namespace App\Views\SolicitudesReabastecimientoAtencion\Service;

use App\Data\AlmacenesData;
use App\Data\LotesProductosData;
use App\Models\SolicitudReabastecimientoDetalle;
use App\Models\SolicitudReabastecimientoDetalleLog;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalle;
use App\Shared\Responses\ApiResponse;
use App\Views\SolicitudesReabastecimientoAtencion\Data\PrestamosData;
use Illuminate\Support\Facades\DB;

class PrestamosService
{
    public static function get_prestamos_por_solicitud(int $id_solicitud_reabastecimiento)
    {
        $data = PrestamosData::get_prestamos_por_solicitud($id_solicitud_reabastecimiento);
        return ApiResponse::success($data);
    }

    public static function crear_prestamo(
        int $id_solicitud_reabastecimiento,
        int $id_almacen_prestamista,
        int $id_empleado_registro,
        array $detalles,
        ?string $fecha_limite_devolucion,
        ?string $observacion,
    ) {
        try {
            DB::beginTransaction();

            $correlativoData = PrestamosData::get_nuevo_correlativo($id_almacen_prestamista);

            // Obtener el almacén solicitante de la cabecera de la solicitud
            $almacen_solicitante = AlmacenesData::get_almacenes(
                id_almacen: $id_solicitud_reabastecimiento
            );
            $id_almacen_solicitante = $almacen_solicitante['id_almacen'];

            $id_prestamo = PrestamosData::crear_prestamo(
                $id_solicitud_reabastecimiento,
                $id_almacen_solicitante,
                $id_almacen_prestamista,
                $id_empleado_registro,
                $correlativoData['correlativo'],
                $correlativoData['numero_correlativo'],
                now()->toDateTimeString(),
                $fecha_limite_devolucion,
                $observacion
            );

            foreach ($detalles as $detalle) {
                $srd = SolicitudReabastecimientoDetalle::find($detalle['id_solicitud_reabastecimiento_detalle']);
                if (!$srd) continue;

                $srd->estado = EstadoSolicitudDetalle::SolicitandoPrestamo->value;
                $srd->save();

                // Registrar trazabilidad en la solicitud original
                SolicitudReabastecimientoDetalleLog::create([
                    'id_solicitud_reabastecimiento_detalle' => $srd->id,
                    'id_empleado' => $id_empleado_registro,
                    'descripcion' => EstadoSolicitudDetalle::SolicitandoPrestamo->getGlosa(),
                    'estado' => EstadoSolicitudDetalle::SolicitandoPrestamo->value,
                    'created_at' => now(),
                ]);

                $cantidad_solicitada = (float) $detalle['cantidad_solicitada'];
                $cantidad_solicitada_base = $cantidad_solicitada * (float) $srd->contenido_por_presentacion;

                // VALIDACIÓN DE STOCK EN TIEMPO REAL
                $lotes_disponibles = LotesProductosData::get_lotes_disponibles($id_almacen_prestamista, $srd->id_producto);
                $stock_total_base = 0;
                foreach ($lotes_disponibles as $lote) {
                    $stock_total_base += (float)$lote->stock_actual_base;
                }

                if ($stock_total_base < $cantidad_solicitada_base) {
                    $nombre_producto = DB::table('producto')->where('id', $srd->id_producto)->value('nombre');
                    $stock_formateado = round($stock_total_base / $srd->contenido_por_presentacion, 2);
                    throw new \Exception("¡Ups! El stock de '{$nombre_producto}' ha cambiado. Disponible: {$stock_formateado}, Solicitado: {$cantidad_solicitada}. La operación fue abortada.");
                }

                $id_detalle = PrestamosData::crear_detalle(
                    (int) $id_prestamo,
                    (int) $detalle['id_solicitud_reabastecimiento_detalle'],
                    (int) $srd->id_producto,
                    (int) $srd->id_unidad_medida,
                    (float) $srd->contenido_por_presentacion,
                    $cantidad_solicitada,
                    $cantidad_solicitada_base,
                    $detalle['comentario'] ?? null,
                );

                // Registrar trazabilidad inicial del préstamo
                PrestamosData::crear_detalle_log(
                    id_prestamo_detalle: $id_detalle,
                    id_empleado: $id_empleado_registro,
                );
            }

            $prestamoCreado = PrestamosData::get_prestamo_por_id((int) $id_prestamo);
            $prestamoCreado['detalles'] = PrestamosData::get_detalles_por_prestamo((int) $id_prestamo);

            DB::commit();

            return ApiResponse::success($prestamoCreado, 'Préstamo solicitado correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Ocurrió un error al procesar el préstamo: ' . $e->getMessage());
        }
    }

    public static function get_prestamo_por_id(int $id_prestamo)
    {
        $cabecera = PrestamosData::get_prestamo_por_id($id_prestamo);
        if (!$cabecera) return ApiResponse::error('Préstamo no encontrado');

        $cabecera['detalles'] = PrestamosData::get_detalles_por_prestamo($id_prestamo);
        return ApiResponse::success($cabecera);
    }
}
