<?php

namespace App\Modules\OrdenesCompraRecepcionTransferencias\Service;

use App\Modules\OrdenesCompraRecepcionTransferencias\Data\RecepcionesData;
use App\Shared\Responses\ApiResponse;

class RecepcionesService
{
    /**
     * Obtener el historial de recepciones
     */
    public static function get_recepciones(int $id_transferencia)
    {
        $recepciones = RecepcionesData::get_recepciones(
            id_transferencia: $id_transferencia,
        );
        $ids_recepciones = array_map(fn($r) => $r->id_recepcion, $recepciones);
        $detalles_recepciones = RecepcionesData::get_detalles_recepcion($ids_recepciones);

        foreach ($recepciones as $recepcion) {
            $recepcion->evidencias = $recepcion->evidencias ? json_decode($recepcion->evidencias) : null;
            $recepcion->detalles = $detalles_recepciones[$recepcion->id_recepcion] ?? [];
        }

        return ApiResponse::success($recepciones);
    }

    /**
     * Registrar la recepcion de una transferencia
     */
    public static function registrar_recepcion(){
        
    }
}
