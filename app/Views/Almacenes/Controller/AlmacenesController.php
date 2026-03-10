<?php

namespace App\Views\Almacenes\Controller;

use App\Shared\Responses\ApiResponse;
use App\Views\Almacenes\Service\AlmacenesService;
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

    public function crear_almacen(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:128',
            'descripcion' => 'nullable|string',
            'es_principal' => 'required|boolean',
        ], [
            'nombre.required' => 'El nombre es obligatorio',
            'es_principal.required' => 'Debe indicar si es almacén principal',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = AlmacenesService::crear_almacen(
            $request->nombre,
            $request->descripcion ?? null,
            $request->es_principal
        );

        return response()->json($result);
    }

}
