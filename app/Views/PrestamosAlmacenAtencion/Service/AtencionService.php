<?php

namespace App\Views\PrestamosAlmacenAtencion\Service;

use App\Shared\Responses\ApiResponse;
use App\Views\PrestamosAlmacenAtencion\Data\PrestamosData;
use App\Views\PrestamosAlmacenAtencion\Data\PrestamosDetalleData;
use App\Views\PrestamosAlmacenAtencion\Data\EntregasData;
use App\Views\PrestamosAlmacenAtencion\Data\EntregasDetalleData;
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
     * Obtiene los detalles de un préstamo e historial de entregas
     */
    public static function get_detalles_prestamo(int $id_prestamo)
    {
        $entregas = EntregasData::get_entregas_por_prestamo($id_prestamo);
        
        foreach ($entregas as $entrega) {
            $entrega->evidencias = $entrega->evidencias ? json_decode($entrega->evidencias) : null;
            $entrega->detalles = EntregasDetalleData::get_detalles_entrega((int) $entrega->id_entrega);
        }

        $data = [
            'detalles' => PrestamosDetalleData::get_detalles_prestamo($id_prestamo),
            'entregas' => $entregas
        ];
        return ApiResponse::success($data);
    }

    /**
     * Cambia el estado de un producto del préstamo (Aprobado/Rechazado)
     */
    public static function cambiar_estado_detalle(int $id_prestamo_detalle, int $id_empleado, string $nuevo_estado, ?string $comentario = null)
    {
        return DB::transaction(function () use ($id_prestamo_detalle, $id_empleado, $nuevo_estado, $comentario) {
            
            // Resolvemos el Enum para obtener la glosa
            $estadoEnum = \App\Shared\Enums\PrestamoAlmacen\EstadoDetallePrestamo::from($nuevo_estado);
            $glosa = $estadoEnum->getGlosa();

            // Actualizar estado
            PrestamosDetalleData::update_detalle_estado($id_prestamo_detalle, $nuevo_estado, $comentario);

            // Registrar log (Usamos la glosa como comentario por defecto si no hay uno personalizado)
            PrestamosDetalleData::insert_detalle_log(
                $id_prestamo_detalle, 
                $id_empleado, 
                $nuevo_estado, 
                $comentario ?? $glosa
            );

            return ApiResponse::success(null, 'Estado del producto actualizado correctamente');
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
