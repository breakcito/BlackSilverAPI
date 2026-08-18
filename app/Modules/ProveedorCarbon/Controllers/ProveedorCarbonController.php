<?php

namespace App\Modules\ProveedorCarbon\Controllers;

use App\Modules\ProveedorCarbon\Services\ProveedorCarbonService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProveedorCarbonController
{
    public function get_tipos_por_proveedor(int $id_proveedor): JsonResponse
    {
        return response()->json(
            ProveedorCarbonService::get_tipos_por_proveedor($id_proveedor)
        );
    }

    /**
     * Body esperado: { tipos_carbon: [int, int, ...] }
     */
    public function set_tipos_por_proveedor(Request $request, int $id_proveedor): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tipos_carbon' => 'present|array',
            'tipos_carbon.*' => 'integer',
        ], [
            'tipos_carbon.required' => 'El campo tipos_carbon es requerido',
            'tipos_carbon.array' => 'tipos_carbon debe ser un arreglo',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        $ids = array_map('intval', (array) $request->input('tipos_carbon', []));

        return response()->json(
            ProveedorCarbonService::set_tipos_por_proveedor($id_proveedor, $ids)
        );
    }
}