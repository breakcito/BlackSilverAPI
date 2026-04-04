<?php

namespace App\Views\SolicitudesReabastecimiento\Service;

use App\Shared\Responses\ApiResponse;
use App\Views\SolicitudesReabastecimiento\Data\EntregasData;

class EntregasService
{
    /**
     * Obtener el historial de entregas y sus detalles de una solicitud 
     * hechas por logistica o por un prestamo
     */
    public static function get_historial_entregas(int $id_solicitud)
    {
        $data_logistica = EntregasData::get_historial_entregas_logistica($id_solicitud);

        foreach ($data_logistica as $entrega) {
            $entrega->evidencias = $entrega->evidencias ? json_decode($entrega->evidencias) : null;
            $entrega->detalles = EntregasData::get_detalles_entrega_logistica((int) $entrega->id_reabastecimiento_entrega);
        }

        $data_prestamo = EntregasData::get_historial_entregas_prestamo($id_solicitud);

        foreach ($data_prestamo as $entrega) {
            $entrega->evidencias = $entrega->evidencias ? json_decode($entrega->evidencias) : null;
            $entrega->detalles = EntregasData::get_detalles_entrega_prestamo((int) $entrega->id_reabastecimiento_entrega);
        }

        return ApiResponse::success([
            'logistica' => $data_logistica,
            'prestamo' => $data_prestamo
        ]);
    }
}
