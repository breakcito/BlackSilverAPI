<?php

namespace App\Modules\PrestamosAlmacenAtencion\Service;

use App\Shared\Enums\PrestamoAlmacen\EstadoPrestamoDetalle;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalle;
use App\Shared\Responses\ApiResponse;
use App\Modules\PrestamosAlmacenAtencion\Data\PrestamosData;
use App\Modules\PrestamosAlmacenAtencion\Data\PrestamosDetalleData;
use App\Modules\PrestamosAlmacenAtencion\Data\EntregasData;
use App\Modules\SolicitudesReabastecimientoAtencion\Data\SolicitudesDetalleData;
use Illuminate\Support\Facades\DB;

class AtencionService
{
    /**
     * Obtiene los préstamos por almacén y periodo
     */
    public static function get_prestamos(int $id_almacen, string $mes, string $yearcito)
    {
        $data = PrestamosData::get_prestamos_por_almacen($id_almacen, $mes, $yearcito);
        return ApiResponse::success($data);
    }

    /**
     * Obtiene los detalles de un préstamo
     */
    public static function get_detalles_prestamo(int $id_prestamo)
    {
        $data = [
            'detalles' => PrestamosDetalleData::get_detalles_prestamo($id_prestamo),
        ];

        return ApiResponse::success($data);
    }

    /**
     * Obtiene el historial de entregas de un préstamo
     */
    public static function get_historial_entregas(int $id_prestamo)
    {
        $entregas = EntregasData::get_entregas_by_prestamo($id_prestamo);

        foreach ($entregas as $entrega) {
            $entrega->evidencias = $entrega->evidencias ? json_decode($entrega->evidencias) : null;
            $entrega->detalles = EntregasData::get_detalles_entrega((int) $entrega->id_prestamo_entrega);
        }

        return ApiResponse::success($entregas);
    }

    /**
     * Cambia el estado de uno o varios productos del préstamo (Aprobado/Rechazado)
     */
    public static function cambiar_estado_detalle(array $ids_detalles, int $id_empleado, string $nuevo_estado, ?string $comentario = null)
    {
        return DB::transaction(function () use ($ids_detalles, $id_empleado, $nuevo_estado, $comentario) {

            // Resolvemos el Enum para obtener la glosa
            $estadoEnum = EstadoPrestamoDetalle::from($nuevo_estado);
            $glosa = $estadoEnum->getGlosa();

            foreach ($ids_detalles as $id_prestamo_detalle) {
                // Actualizar estado
                PrestamosDetalleData::update_detalle_estado((int) $id_prestamo_detalle, $nuevo_estado);

                // Registrar log local (Préstamo)
                PrestamosDetalleData::insert_detalle_log(
                    (int) $id_prestamo_detalle,
                    $id_empleado,
                    $nuevo_estado,
                    $comentario ?? $glosa
                );

                // --- TRAZABILIDAD CRUZADA (REABASTECIMIENTO) ---
                $itemPrestamo = PrestamosDetalleData::get_detalle_by_id((int) $id_prestamo_detalle);
                if ($itemPrestamo && $itemPrestamo->id_solicitud_reabastecimiento_detalle) {
                    $id_sol_det = (int) $itemPrestamo->id_solicitud_reabastecimiento_detalle;

                    // Obtenemos el correlativo del préstamo para el log
                    $correlativoPRT = PrestamosData::get_correlativo((int) $itemPrestamo->id_prestamo_almacen);

                    $descripcionLog = "El producto cambió a estado [{$glosa}] en el préstamo {$correlativoPRT}";
                    if ($comentario) {
                        $descripcionLog .= ". Comentario: {$comentario}";
                    }

                    // Insertar log en la solicitud de reabastecimiento
                    SolicitudesDetalleData::insert_log_simple(
                        $id_sol_det,
                        $id_empleado,
                        $nuevo_estado,
                        $descripcionLog
                    );

                    // --- ACTUALIZACIÓN DE ESTADO DEL DETALLE DE SOLICITUD ---
                    $estadoSolicitud = null;
                    if ($nuevo_estado === EstadoPrestamoDetalle::Aprobado->value) {
                        $estadoSolicitud = EstadoSolicitudDetalle::Aprobado->value;
                    } elseif ($nuevo_estado === EstadoPrestamoDetalle::Rechazado->value) {
                        $estadoSolicitud = EstadoSolicitudDetalle::Aprobado->value; // Regresa a aprobado para intentar otro almacén
                    } elseif ($nuevo_estado === EstadoPrestamoDetalle::EnDespacho->value) {
                        $estadoSolicitud = EstadoSolicitudDetalle::EnDespacho->value;
                    }

                    if ($estadoSolicitud) {
                        SolicitudesDetalleData::update_detalle_estado($id_sol_det, $estadoSolicitud, $id_empleado);
                    }
                }
            }

            $mensaje = count($ids_detalles) > 1
                ? 'Estado de los productos actualizado correctamente'
                : 'Estado del producto actualizado correctamente';

            return ApiResponse::success(null, $mensaje);
        });
    }

    /**
     * Obtiene la trazabilidad de un detalle de préstamo
     */
    public static function get_trazabilidad(int $id_detalle)
    {
        $data = PrestamosDetalleData::get_detalle_logs($id_detalle);
        return ApiResponse::success($data);
    }
}
