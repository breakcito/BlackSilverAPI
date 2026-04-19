<?php

namespace App\Modules\PrestamosAlmacen\Controller;

use App\Shared\Responses\ApiResponse;
use App\Modules\PrestamosAlmacen\Service\AuxService;
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
     * Obtiene los almacenes secundarios (prestamistas posibles)
     */
    public function get_almacenes_secundarios(): JsonResponse
    {
        $result = AuxService::get_almacenes(false);
        return response()->json($result);
    }

    /**
     * Obtiene los almacenes principales (logistica)
     */
    public function get_almacenes_principales(): JsonResponse
    {
        $result = AuxService::get_almacenes(true);
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

    public function get_personal_externo(Request $request): JsonResponse
    {
        $result = AuxService::get_personal_externo();
        return response()->json($result);
    }

    public function crear_personal_externo(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string',
            'apellido' => 'nullable|string',
            'dni' => 'nullable|string',
        ]);

        $result = AuxService::crear_personal_externo(
            nombre: $request->input('nombre'),
            apellido: $request->input('apellido'),
            dni: $request->input('dni')
        );

        return response()->json($result);
    }
}
