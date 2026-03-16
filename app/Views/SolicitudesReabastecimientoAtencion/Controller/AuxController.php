<?php

namespace App\Views\SolicitudesReabastecimientoAtencion\Controller;

use App\Shared\Responses\ApiResponse;
use App\Views\SolicitudesReabastecimientoAtencion\Service\AuxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuxController extends Controller
{

    public function get_almacenes(Request $request): JsonResponse
    {
        $es_principal = (bool)$request->input('es_principal');

        $result = AuxService::get_almacenes($es_principal);

        return response()->json($result);
    }

    public function get_empleados(Request $request): JsonResponse
    {

        $result = AuxService::get_empleados();

        return response()->json($result);
    }

    /**
     * Obtener lotes disponibles para productos en un almacén.
     */
    public function get_lotes_disponibles(Request $request): JsonResponse
    {
        $id_producto = $request->input('id_producto');
        $id_almacen = $request->input('id_almacen');

        if (is_null($id_producto) || is_null($id_almacen)) {
            return response()->json(ApiResponse::error('id_producto e id_almacen son requeridos'));
        }

        $ids_productos = is_array($id_producto) ? array_map('intval', $id_producto) : [(int) $id_producto];

        $result = AuxService::get_lotes_disponibles($ids_productos, (int) $id_almacen);

        return response()->json($result);
    }
}
