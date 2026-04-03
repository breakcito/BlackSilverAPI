<?php

namespace App\Views\PrestamosAlmacen\Service;

use App\Shared\Responses\ApiResponse;
use App\Views\PrestamosAlmacen\Data\PrestamosData;

class PrestamosService
{
    /**
     * Obtiene los préstamos por almacén y periodo
     */
    public static function get_prestamos_por_almacen(int $id_almacen, int $mes, int $yearcito)
    {
        $data = PrestamosData::get_prestamos_por_almacen($id_almacen, $mes, $yearcito);
        return ApiResponse::success($data);
    }

    /**
     * Obtiene los detalles de un préstamo
     */
    public static function get_detalles_prestamo(int $id_prestamo)
    {
        $data = PrestamosData::get_detalles_prestamo($id_prestamo);
        return ApiResponse::success($data);
    }

    /**
     * Obtiene la trazabilidad de un detalle de préstamo
     */
    public static function get_trazabilidad(int $id_detalle)
    {
        $data = PrestamosData::get_detalle_logs($id_detalle);
        return ApiResponse::success($data);
    }
}
