<?php

namespace App\Views\SolicitudesReabastecimientoAtencion\Service;

use App\Models\SolicitudReabastecimientoDetalle;
use App\Models\SolicitudReabastecimientoDetalleLog;
use App\Shared\Enums\PrestamoAlmacen\EstadoDetallePrestamo;
use App\Shared\Enums\PrestamoAlmacen\EstadoPrestamo;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalle;
use App\Shared\Enums\Periodo;
use App\Shared\Helpers\CorrelativoHelper;
use App\Shared\Responses\ApiResponse;
use App\Views\SolicitudesReabastecimientoAtencion\Data\PrestamosData;
use App\Views\SolicitudesReabastecimientoAtencion\Data\PrestamosDetalleData;
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
        ?string $fecha_limite_devolucion,
        array $detalles
    ) {
        try {
            DB::beginTransaction();

            $correlativoData = CorrelativoHelper::generar(
                'prestamo_almacen',
                'PRST',
                [],
                5,
                Periodo::Anual,
                'created_at'
            );

            // Obtener el almacén solicitante de la cabecera de la solicitud
            $id_almacen_solicitante = (int) DB::table('solicitud_reabastecimiento')
                ->where('id', $id_solicitud_reabastecimiento)
                ->value('id_almacen_solicitante');

            $id_prestamo = PrestamosData::crear_prestamo_cabecera(
                $id_solicitud_reabastecimiento,
                $id_almacen_solicitante,
                $id_almacen_prestamista,
                $id_empleado_registro,
                $correlativoData['correlativo'],
                $correlativoData['numero_correlativo'],
                now()->toDateTimeString(),
                $fecha_limite_devolucion,
                EstadoPrestamo::Generado->value
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
                $lotes_disponibles = PrestamosData::get_lotes_disponibles_por_almacen_y_producto($srd->id_producto, $id_almacen_prestamista);
                $stock_total_base = 0;
                foreach ($lotes_disponibles as $lote) {
                    $stock_total_base += (float)$lote->stock_actual_base;
                }

                if ($stock_total_base < $cantidad_solicitada_base) {
                    $nombre_producto = DB::table('producto')->where('id', $srd->id_producto)->value('nombre');
                    $stock_formateado = round($stock_total_base / $srd->contenido_por_presentacion, 2);
                    throw new \Exception("¡Ups! El stock de '{$nombre_producto}' ha cambiado. Disponible: {$stock_formateado}, Solicitado: {$cantidad_solicitada}. La operación fue abortada.");
                }

                $id_detalle = PrestamosDetalleData::crear_prestamo_detalle(
                    (int) $id_prestamo,
                    (int) $detalle['id_solicitud_reabastecimiento_detalle'],
                    (int) $srd->id_producto,
                    (int) $srd->id_unidad_medida,
                    (float) $srd->contenido_por_presentacion,
                    $cantidad_solicitada,
                    $cantidad_solicitada_base,
                    $detalle['comentario'] ?? null,
                    EstadoDetallePrestamo::Pendiente->value
                );

                // Registrar trazabilidad inicial del préstamo
                \App\Models\PrestamoAlmacenDetalleLog::create([
                    'id_prestamo_almacen_detalle' => $id_detalle,
                    'id_empleado' => $id_empleado_registro,
                    'descripcion' => EstadoDetallePrestamo::Pendiente->getGlosa(),
                    'estado' => EstadoDetallePrestamo::Pendiente->value,
                    'created_at' => now(),
                ]);
            }

            $prestamoCreado = PrestamosData::get_prestamo_por_id((int) $id_prestamo);
            $prestamoCreado->detalles = PrestamosDetalleData::get_detalles_por_prestamo((int) $id_prestamo);

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

        $cabecera->detalles = PrestamosDetalleData::get_detalles_por_prestamo($id_prestamo);
        return ApiResponse::success($cabecera);
    }

    // Auxiliares movidos aquí o a AuxService
    public static function get_almacenes_con_stock_multiple_productos(array $ids_productos, int $id_almacen_excluido)
    {
        $data = PrestamosData::get_almacenes_con_stock_multiple_productos($ids_productos, $id_almacen_excluido);
        return ApiResponse::success($data);
    }

    public static function get_lotes_disponibles_por_almacen_y_producto(int $id_producto, int $id_almacen)
    {
        $data = PrestamosData::get_lotes_disponibles_por_almacen_y_producto($id_producto, $id_almacen);
        return ApiResponse::success($data);
    }
}
