<?php

namespace App\Views\PrestamosAlmacen\Service;

use App\Shared\Responses\ApiResponse;
use App\Views\PrestamosAlmacen\Data\EntregasData;
use App\Views\PrestamosAlmacen\Data\EntregasDetalleData;

class EntregasService
{
    /**
     * Obtiene el historial de entregas de un préstamo con sus detalles.
     */
    public static function get_historial_entregas(int $id_prestamo)
    {
        $data = EntregasData::get_entregas_por_prestamo($id_prestamo);
        foreach ($data as $entrega) {
            $entrega->evidencias = $entrega->evidencias ? json_decode($entrega->evidencias) : null;
            $entrega->detalles = EntregasDetalleData::get_detalles_entrega(id_entrega: (int) $entrega->id_entrega);
        }
        return ApiResponse::success($data);
    }
}
