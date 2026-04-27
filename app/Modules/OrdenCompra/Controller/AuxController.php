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
