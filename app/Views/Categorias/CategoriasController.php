<?php

namespace App\Views\Categorias;

use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;
use App\Shared\Enums\Producto\TipoProducto;
use App\Shared\Enums\Producto\ClasificacionBien;

class CategoriasController extends Controller
{
    /**
     * Listar categorías
     */
    public function get_categorias(Request $request): JsonResponse
    {
        $result = CategoriasService::get_categorias();

        return response()->json($result);
    }

    /**
     * Crear una nueva categoría
     */
    public function crear_categoria(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:128',
            'descripcion' => 'nullable|string',
            'tipo_requerimiento' => ['required', new Enum(TipoProducto::class)],
            'clasificacion_bien' => ['nullable', new Enum(ClasificacionBien::class)],
        ], [
            'nombre.required' => 'El nombre es obligatorio',
            'tipo_requerimiento.required' => 'El tipo de requerimiento es obligatorio',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = CategoriasService::crear_categoria($request->all());

        return response()->json($result);
    }
}
