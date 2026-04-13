<?php

namespace App\Modules\SolicitudesReabastecimientoAtencion\Controller;

use App\Shared\Responses\ApiResponse;
use App\Modules\SolicitudesReabastecimientoAtencion\Service\AuxService;
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
        $ids_productos = $request->input('ids_productos');
        $id_almacen = $request->input('id_almacen');

        if (is_null($ids_productos) || is_null($id_almacen)) {
            return response()->json(ApiResponse::error('ids_productos e id_almacen son requeridos'));
        }

        $ids_productos = is_array($ids_productos) ? array_map('intval', $ids_productos) : [(int) $ids_productos];

        $result = AuxService::get_lotes_disponibles((int) $id_almacen, $ids_productos);

        return response()->json($result);
    }

    public function get_almacenes_con_stock(Request $request): JsonResponse
    {
        $ids_productos = (array) $request->query('ids_productos');
        $id_almacen_excluido = (int) $request->query('id_almacen_excluido');

        if (empty($ids_productos)) {
            return response()->json(ApiResponse::error('ids_productos es requerido'), 400);
        }

        $result = AuxService::get_almacenes_con_stock($id_almacen_excluido, $ids_productos);
        return response()->json($result);
    }
}
