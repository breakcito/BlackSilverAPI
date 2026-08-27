<?php

namespace App\Modules\TipoCarbon\Controllers;

use App\Modules\TipoCarbon\Service\TipoCarbonService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TipoCarbonController
{
    public function get_tipos(Request $request): JsonResponse
    {
        $raw = $request->query('para_compra');
        $soloParaCompra = ($raw === null || $raw === '')
            ? null
            : filter_var($raw, FILTER_VALIDATE_BOOLEAN);

        return response()->json(TipoCarbonService::get_tipos(solo_para_compra: $soloParaCompra));
    }

    public function get_tipo_by_id_route(int $id_tipo_carbon): JsonResponse
    {
        return response()->json(TipoCarbonService::get_tipo_by_id(id_tipo_carbon: $id_tipo_carbon));
    }

    public function crear_tipo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:128',
            'codigo' => 'nullable|string|max:32',
            'para_compra' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        return response()->json(TipoCarbonService::crear_tipo(
            nombre: (string) $request->input('nombre'),
            codigo: $request->input('codigo'),
            para_compra: (bool) $request->boolean('para_compra')
        ));
    }

    public function actualizar_tipo(Request $request, int $id_tipo_carbon): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:128',
            'codigo' => 'nullable|string|max:32',
            'para_compra' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        return response()->json(TipoCarbonService::actualizar_tipo(
            id_tipo_carbon: $id_tipo_carbon,
            nombre: (string) $request->input('nombre'),
            codigo: $request->input('codigo'),
            para_compra: (bool) $request->boolean('para_compra')
        ));
    }

    public function eliminar_tipo(int $id_tipo_carbon): JsonResponse
    {
        return response()->json(TipoCarbonService::eliminar_tipo(id_tipo_carbon: $id_tipo_carbon));
    }

    public function get_variantes(int $id_tipo_carbon): JsonResponse
    {
        return response()->json(TipoCarbonService::get_variantes(id_tipo_carbon: $id_tipo_carbon));
    }

    public function get_variantes_opciones(int $id_tipo_carbon): JsonResponse
    {
        return response()->json(TipoCarbonService::get_variantes_opciones(id_tipo_carbon: $id_tipo_carbon));
    }

    /**
     * @param Request $request 'variantes' debe ser un array de ids.
     */
    public function set_variantes(Request $request, int $id_tipo_carbon): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'variantes' => 'present|array',
            'variantes.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        $ids = array_map('intval', (array) $request->input('variantes', []));

        return response()->json(TipoCarbonService::set_variantes(
            id_tipo_carbon: $id_tipo_carbon,
            ids_variante: $ids
        ));
    }
}