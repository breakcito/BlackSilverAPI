<?php

namespace App\Modules\SolicitudesReabastecimientoAtencion\Service;

use App\Shared\Responses\ApiResponse;
use App\Modules\SolicitudesReabastecimientoAtencion\Data\RecepcionesData;

class RecepcionesService
{

    // Obtener el historial de recepciones de una entrega
    public static function get_historial_recepciones(int $id_entrega)
    {
        $data = RecepcionesData::get_recepciones_by_entrega($id_entrega);

        foreach ($data as $recepcion) {
            $recepcion->evidencias = $recepcion->evidencias ? json_decode($recepcion->evidencias) : null;
            $recepcion->detalles = RecepcionesData::get_detalles_recepcion((int) $recepcion->id_reabastecimiento_recepcion);
        }

        return ApiResponse::success($data);
    }
}
