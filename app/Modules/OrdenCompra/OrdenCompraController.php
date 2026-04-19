<?php

namespace App\Modules\OrdenCompra;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Modules\OrdenCompra\OrdenCompraService;

class OrdenCompraController
{
    /**
     * Listar todas las órdenes de compra
     */
    public function get_listado(): JsonResponse
    {
        $result = OrdenCompraService::listar();
        return response()->json($result);
    }

    /**
     * Obtener los detalles de una OC específica
     */
    public function get_detalles(Request $request): JsonResponse
    {
        $id_orden_compra = (int) $request->query('id_orden_compra');

        if (!$id_orden_compra) {
            return response()->json(\App\Shared\Responses\ApiResponse::error('Debe indicar el id de la Orden de Compra.'));
        }

        $result = OrdenCompraService::get_detalles($id_orden_compra);
        return response()->json($result);
    }
}
