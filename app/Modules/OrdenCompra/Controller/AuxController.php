<?php

namespace App\Modules\OrdenCompra\Controller;

use App\Modules\OrdenCompra\Service\AuxService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuxController
{
    /**
     * Catálogo de almacenes para el módulo de OC.
     */
    public function get_almacenes(): JsonResponse
    {
        return response()->json(AuxService::get_almacenes());
    }

    /**
     * Obtener lotes disponibles en el almacén de destino para productos de OC.
     */
    public function get_lotes_disponibles(Request $request): JsonResponse
    {
        $id_almacen = (int) $request->input('id_almacen_recepcionista');
        $id_productos = $request->input('id_productos');

        if (!$id_almacen || empty($id_productos) || !is_array($id_productos)) {
            return response()->json(ApiResponse::error('ID de almacén y arreglo de productos son requeridos'), 400);
        }

        return response()->json(AuxService::get_lotes_disponibles($id_almacen, $id_productos));
    }
}
