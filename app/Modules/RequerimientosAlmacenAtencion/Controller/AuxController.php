<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Controller;

use App\Shared\Responses\ApiResponse;
use App\Modules\RequerimientosAlmacenAtencion\Service\AuxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuxController extends Controller
{
    /**
     * Obtener almacenes donde el usuario es responsable.
     */
    public function get_almacenes_autorizados(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $result = AuxService::get_almacenes_autorizados($authUser->id_empleado);

        return response()->json($result);
    }

    public function get_data_to_registro(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $result = AuxService::get_data_to_registro($authUser->id_empleado);

        return response()->json($result);
    }

    public function get_minas_by_almacen(Request $request): JsonResponse
    {
        $id_almacen = $request->query('id_almacen');
        if (!$id_almacen) {
            return response()->json(ApiResponse::error('Almacen requerido'), 400);
        }

        $result = AuxService::get_minas_by_almacen((int) $id_almacen);

        return response()->json($result);
    }

    public function get_data_by_mina(Request $request): JsonResponse
    {
        $id_mina = $request->query('id_mina');
        if (!$id_mina) {
            return response()->json(ApiResponse::error('Mina requerida'), 400);
        }

        $result = AuxService::get_data_by_mina((int) $id_mina);

        return response()->json($result);
    }

    /**
     * Obtener lotes disponibles para varios productos (Batch).
     */
    public function get_lotes_disponibles(Request $request): JsonResponse
    {
        $id_almacen = $request->query('id_almacen');
        $ids_productos_str = $request->query('ids_productos');

        if (!$id_almacen || !$ids_productos_str) {
            return response()->json(ApiResponse::error('Faltan parámetros: id_almacen o ids_productos'), 400);
        }

        $ids_productos = explode(',', $ids_productos_str);
        $ids_productos = array_map('intval', $ids_productos);

        $lotes = AuxService::get_lotes_disponibles((int) $id_almacen, $ids_productos);

        return response()->json(ApiResponse::success($lotes));
    }
}
