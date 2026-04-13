<?php

namespace App\Modules\PrestamosAlmacenAtencion\Service;

use App\Shared\Responses\ApiResponse;
use App\Modules\PrestamosAlmacenAtencion\Data\RecepcionesData;

class RecepcionesService
{
    /**
     * Obtener el historial de recepciones de una entrega de préstamo
     */
    public static function obtener_historial_recepciones(int $id_entrega)
    {
        $cabeceras = RecepcionesData::get_historial_recepciones($id_entrega);

        foreach ($cabeceras as $cab) {
            $cab->evidencias = $cab->evidencias ? json_decode($cab->evidencias) : null;
            $cab->detalles = RecepcionesData::get_detalles_recepcion($cab->id_recepcion);
        }

        return ApiResponse::success($cabeceras);
    }
}
