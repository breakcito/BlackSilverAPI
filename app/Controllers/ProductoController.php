<?php

namespace App\Controllers;

use App\Services\ProductoService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class ProductoController extends Controller
{
    public function __construct(
        private ProductoService $productoService
    ) {}

    public function get_productos(Request $request): JsonResponse
    {
        $result = $this->productoService->get_productos();

        return response()->json($result);
    }

    public function crear_producto(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_categoria' => 'required|integer|exists:categoria,id',
            'nombre' => 'required|string|max:128',
            'es_fiscalizado' => 'required|boolean',
            'es_perecible' => 'required|boolean',
        ], [
            'id_categoria.required' => 'La categoría es requerida',
            'id_categoria.exists' => 'La categoría seleccionada no existe',
            'nombre.required' => 'El nombre es requerido',
            'es_fiscalizado.required' => 'Debe indicar si es fiscalizado',
            'es_perecible.required' => 'Debe indicar si es perecible',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = $this->productoService->crear_producto(
            $request->id_categoria,
            $request->nombre,
            $request->es_fiscalizado,
            $request->es_perecible
        );

        return response()->json($result);
    }
}
