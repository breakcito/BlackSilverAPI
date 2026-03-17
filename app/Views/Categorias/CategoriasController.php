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
            'tipo_requerimiento' => 'required|string|max:64',
            'clasificacion_bien' => 'nullable|string|max:64',
            'es_consumible' => 'boolean',
            'para_cocina' => 'boolean',
            'para_mina' => 'boolean',
            'ids_consumidoras' => 'array',
            'ids_consumidoras.*' => 'integer',
        ], [
            'nombre.required' => 'El nombre es obligatorio',
            'tipo_requerimiento.required' => 'El tipo de requerimiento es obligatorio',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = CategoriasService::crear_categoria(
            nombre: (string) $request->input('nombre'),
            tipo_requerimiento: (string) $request->input('tipo_requerimiento'),
            descripcion: $request->input('descripcion'),
            clasificacion_bien: $request->input('clasificacion_bien'),
            es_consumible: (bool) $request->boolean('es_consumible'),
            para_cocina: (bool) $request->boolean('para_cocina'),
            para_mina: (bool) $request->boolean('para_mina'),
            ids_consumidoras: (array) $request->input('ids_consumidoras', [])
        );

        return response()->json($result);
    }
}
