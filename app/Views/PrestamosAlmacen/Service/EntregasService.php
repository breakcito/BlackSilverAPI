<?php

namespace App\Views\PrestamosAlmacen\Service;

use App\Shared\Responses\ApiResponse;
use App\Views\PrestamosAlmacen\Data\EntregasData;

class EntregasService
{
    /**
     * Obtiene el historial de entregas de un préstamo con sus detalles.
     */
    public static function get_historial_entregas(int $id_prestamo): array
    {
        $data = EntregasData::get_entregas_por_prestamo($id_prestamo);
        foreach ($data as $entrega) {
            $entrega->evidencias = $entrega->evidencias ? json_decode($entrega->evidencias) : null;
            $entrega->detalles = EntregasData::get_detalles_entrega(id_entrega: (int) $entrega->id_prestamo_entrega);

            // Cargar Recepciones vinculadas a esta entrega
            $entrega->recepciones = EntregasData::get_recepciones_por_entrega((int) $entrega->id_prestamo_entrega);
            foreach ($entrega->recepciones as $recepcion) {
                $recepcion->evidencias = $recepcion->evidencias ? json_decode($recepcion->evidencias) : null;
                $recepcion->detalles = EntregasData::get_detalles_recepcion((int) $recepcion->id_recepcion);
            }
        }
        return ApiResponse::success($data);
    }
}
