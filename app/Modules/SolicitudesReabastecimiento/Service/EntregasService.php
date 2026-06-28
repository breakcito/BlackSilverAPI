<?php

namespace App\Modules\SolicitudesReabastecimiento\Service;

use App\Shared\Responses\ApiResponse;
use App\Modules\SolicitudesReabastecimiento\Data\EntregasData;

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
            
            // Cargar recepciones de logistica
            $recepciones = \App\Modules\SolicitudesReabastecimiento\Data\RecepcionesData::get_historial_recepciones((int) $entrega->id_reabastecimiento_entrega);
            foreach ($recepciones as $rec) {
                $rec->evidencias = $rec->evidencias ? json_decode($rec->evidencias) : null;
                $rec->detalles = \App\Modules\SolicitudesReabastecimiento\Data\RecepcionesData::get_detalles_recepcion((int) $rec->id_recepcion);
            }
            $entrega->recepciones = $recepciones;
        }

        $data_prestamo = EntregasData::get_historial_entregas_prestamo($id_solicitud);

        foreach ($data_prestamo as $entrega) {
            $entrega->evidencias = $entrega->evidencias ? json_decode($entrega->evidencias) : null;
            $entrega->detalles = EntregasData::get_detalles_entrega_prestamo((int) $entrega->id_prestamo_entrega);

            // Cargar recepciones de prestamo
            $recepciones = \App\Modules\SolicitudesReabastecimiento\Data\RecepcionesPrestamoData::get_historial_recepciones((int) $entrega->id_prestamo_entrega);
            foreach ($recepciones as $rec) {
                $rec->evidencias = $rec->evidencias ? json_decode($rec->evidencias) : null;
                $rec->detalles = \App\Modules\SolicitudesReabastecimiento\Data\RecepcionesPrestamoData::get_detalles_recepcion((int) $rec->id_recepcion);
            }
            $entrega->recepciones = $recepciones;
        }

        return ApiResponse::success([
            'logistica' => $data_logistica,
            'prestamo' => $data_prestamo
        ]);
    }
}
