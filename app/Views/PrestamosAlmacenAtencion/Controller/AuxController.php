<?php

namespace App\Views\PrestamosAlmacenAtencion\Controller;

use App\Shared\Responses\ApiResponse;
use App\Views\PrestamosAlmacenAtencion\Service\AuxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuxController extends Controller
{
    /**
     * Obtiene los almacenes donde el empleado es responsable
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

    /**
     * Obtiene los empleados activos (para selector entregador/receptor)
     */
    public function get_empleados(): JsonResponse
    {
        $result = AuxService::get_empleados();
        return response()->json($result);
    }

    /**
     * Obtiene los lotes disponibles para el despacho
     */
    public function get_lotes_disponibles(Request $request): JsonResponse
    {
        $id_producto = (int) $request->query('id_producto');
        $id_almacen  = (int) $request->query('id_almacen');

        if (!$id_producto || !$id_almacen) {
            return response()->json(ApiResponse::error('id_producto e id_almacen son requeridos'), 400);
        }

        $result = AuxService::get_lotes_disponibles($id_producto, $id_almacen);
        return response()->json($result);
    }

    /**
     * Obtiene las unidades de medida
     */
    public function get_unidades_medida(): JsonResponse
    {
        $result = AuxService::get_unidades_medida();
        return response()->json($result);
    }

    /**
     * Obtiene los lotes destino para la recepción de reposición
     */
    public function get_lotes_destino(Request $request): JsonResponse
    {
        $id_almacen = (int) $request->query('id_almacen');
        $id_productos = $request->query('id_productos');

        if (!$id_almacen || empty($id_productos)) {
            return response()->json(ApiResponse::error('id_almacen e id_productos son requeridos'), 400);
        }

        // Asegurar que id_productos sea un array
        if (!is_array($id_productos)) {
            $id_productos = explode(',', $id_productos);
        }

        $id_productos = array_map('intval', $id_productos);

        $result = AuxService::get_lotes_destino($id_almacen, $id_productos);
        return response()->json($result);
    }
}
