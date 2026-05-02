<?php

namespace App\Modules\OrdenesCompraRecepcionTransferencias\Controller;

use App\Modules\OrdenesCompraRecepcionTransferencias\Service\TransferenciasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferenciasController
{
    /**
     * Listar transferencias destinadas al almacén del usuario autenticado,
     * filtradas por mes y año.
     */
    public function get_transferencias(Request $request): JsonResponse
    {
        $id_almacen_destino = (int) $request->query('id_almacen_destino');
        $mes = (int) $request->query('mes');
        $anio = (int) $request->query('anio');

        return response()->json(
            TransferenciasService::get_transferencias($id_almacen_destino, $mes, $anio)
        );
    }

    /**
     * Obtener los detalles (productos) de una transferencia específica.
     */
    public function get_detalles(int $id): JsonResponse
    {
        return response()->json(
            TransferenciasService::get_detalles_transferencia($id)
        );
    }
}
