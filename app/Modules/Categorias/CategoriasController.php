<?php

namespace App\Modules\Categorias;

use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

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
            'tipo_producto' => 'required|string|max:64',
            'clasificacion_bien' => 'nullable|string|max:64',
            'es_consumible' => 'boolean',
            'para_cocina' => 'boolean',
            'para_mina' => 'boolean',
            'es_auditable' => 'boolean',
            'ids_categorias_consumidoras' => 'array',
            'ids_categorias_consumidoras.*' => 'integer',
        ], [
            'nombre.required' => 'El nombre es obligatorio',
            'tipo_producto.required' => 'El tipo de requerimiento es obligatorio',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = CategoriasService::crear_categoria(
            nombre: (string) $request->input('nombre'),
            tipo_producto: (string) $request->input('tipo_producto'),
            descripcion: $request->input('descripcion'),
            clasificacion_bien: $request->input('clasificacion_bien'),
            es_consumible: (bool) $request->boolean('es_consumible'),
            para_cocina: (bool) $request->boolean('para_cocina'),
            para_mina: (bool) $request->boolean('para_mina'),
            es_auditable: (bool) $request->boolean('es_auditable'),
            ids_categorias_consumidoras: (array) $request->input('ids_categorias_consumidoras', [])
        );

        return response()->json($result);
    }

    /**
     * Actualizar los destinos de consumo de una categoría existente
     */
    public function actualizar_consumidoras(Request $request): JsonResponse
    {
        $id_categoria = (int) $request->input('id_categoria');
        $ids_categorias_consumidoras = (array) $request->input('ids_categorias_consumidoras', []);

        $result = CategoriasService::actualizar_consumidoras($id_categoria, $ids_categorias_consumidoras);

        return response()->json($result);
    }
}
