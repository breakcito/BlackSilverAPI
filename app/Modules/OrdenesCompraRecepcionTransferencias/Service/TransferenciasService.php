<?php

namespace App\Modules\OrdenesCompraRecepcionTransferencias\Service;

use App\Modules\OrdenesCompraRecepcionTransferencias\Data\TransferenciasData;
use App\Shared\Responses\ApiResponse;

class TransferenciasService
{
    /**
     * Obtener el historial de transferencias
     */
    public static function get_transferencias(int $id_almacen_destino, int $mes, int $yearcito)
    {
        $cabeceras = TransferenciasData::get_transferencias(
            id_almacen_destino: $id_almacen_destino,
            mes: $mes,
            yearcito: $yearcito
        );

        foreach ($cabeceras as $cab) {
            $cab->evidencias = $cab->evidencias ? json_decode($cab->evidencias) : null;
        }

        return ApiResponse::success($cabeceras);
    }

    /**
     * Obtener detalles de una transferencia
     */
    public static function get_detalles_transferencia(int $id_transferencia)
    {
        return ApiResponse::success(TransferenciasData::get_detalles_transferencia($id_transferencia));
    }
}
