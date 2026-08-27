<?php

namespace App\Modules\LugarExtraccionCarbon\Controllers;

use App\Modules\LugarExtraccionCarbon\Services\LugarExtraccionCarbonService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LugarExtraccionCarbonController
{
    public function get_por_proveedor(int $id_proveedor): JsonResponse
    {
        return response()->json(
            LugarExtraccionCarbonService::get_por_proveedor($id_proveedor)
        );
    }

    /**
     * Body esperado:
     *   { lugares: [ { id_departamento, id_provincia, id_distrito, direccion }, ... ] }
     */
    public function set_para_proveedor(Request $request, int $id_proveedor): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lugares' => 'present|array',
            'lugares.*.id_departamento' => 'required_with:lugares|integer|min:1',
            'lugares.*.id_provincia' => 'required_with:lugares|integer|min:1',
            'lugares.*.id_distrito' => 'required_with:lugares|integer|min:1',
            'lugares.*.direccion' => 'required_with:lugares|string|max:255',
        ], [
            'lugares.*.id_departamento.required_with' => 'Selecciona un departamento',
            'lugares.*.id_provincia.required_with' => 'Selecciona una provincia',
            'lugares.*.id_distrito.required_with' => 'Selecciona un distrito',
            'lugares.*.direccion.required_with' => 'La direccion es obligatoria',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        $lugares = array_map(
            fn(array $l) => [
                'id_departamento' => (int) $l['id_departamento'],
                'id_provincia' => (int) $l['id_provincia'],
                'id_distrito' => (int) $l['id_distrito'],
                'direccion' => trim((string) $l['direccion']),
            ],
            (array) $request->input('lugares', []),
        );

        return response()->json(
            LugarExtraccionCarbonService::set_para_proveedor($id_proveedor, $lugares)
        );
    }
}