<?php

namespace App\Views\PrestamosAlmacen\Controller;

use App\Shared\Responses\ApiResponse;
use App\Views\PrestamosAlmacen\Service\AuxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuxController extends Controller
{
    /**
     * Obtiene los almacenes (principales o secundarios)
     */
    public function get_almacenes(Request $request): JsonResponse
    {
        $es_principal = filter_var($request->query('es_principal'), FILTER_VALIDATE_BOOLEAN);
        $result = AuxService::get_almacenes($es_principal);
        return response()->json($result);
    }

    /**
     * Obtener lotes disponibles para ciertos productos de un almacén.
     */
    public function get_lotes_disponibles(Request $request): JsonResponse
    {
        $ids_productos = $request->input('ids_productos');
        $id_almacen = $request->input('id_almacen');

        if (is_null($ids_productos) || is_null($id_almacen)) {
            return response()->json(ApiResponse::error('ids_productos e id_almacen son requeridos'));
        }

        $ids_productos = is_array($ids_productos) ? array_map('intval', $ids_productos) : [(int) $ids_productos];

        $result = AuxService::get_lotes_disponibles((int) $id_almacen, $ids_productos);

        return response()->json($result);
    }
}
