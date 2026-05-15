<?php

namespace App\Modules\Almacenes\Controller;

use App\Shared\Responses\ApiResponse;
use App\Modules\Almacenes\Service\AlmacenesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class AlmacenesController extends Controller
{
    public function get_almacenes(Request $request): JsonResponse
    {
        $result = AlmacenesService::get_almacenes();

        return response()->json($result);
    }

    public function crear_almacen(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:128',
            'descripcion' => 'nullable|string',
            'es_principal' => 'required|boolean',
            'es_virtual' => 'required|boolean',
        ], [
            'nombre.required' => 'El nombre es obligatorio',
            'es_principal.required' => 'Debe indicar si es almacén principal',
            'es_virtual.required' => 'Debe indicar si es almacén virtual',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $v = $validator->validated();

        $result = AlmacenesService::crear_almacen(
            nombre: (string) $v['nombre'],
            descripcion: isset($v['descripcion']) ? (string) $v['descripcion'] : null,
            es_principal: (bool) $v['es_principal'],
            es_virtual: (bool) $v['es_virtual']
        );

        return response()->json($result);
    }
}
