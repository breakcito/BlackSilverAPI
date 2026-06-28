<?php

namespace App\Modules\Categorias;

use App\Shared\Responses\ApiResponse;
use App\Shared\Enums\_Generic\TipoBien;
use App\Shared\Enums\_Generic\TipoProducto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;

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
            'tipo_producto' => ['required', new Enum(TipoProducto::class)],
            'clasificacion_bien' => ['required', new Enum(TipoBien::class)],
            'para_transporte' => 'boolean',
            'control_por_odometro' => 'boolean',
            'control_por_horometro' => 'boolean',
            'control_por_vueltas' => 'boolean',
            'es_consumible' => 'boolean',
            'para_cocina' => 'boolean',
            'para_mina' => 'boolean',
            'es_auditable' => 'boolean',
        ], [
            'nombre.required' => 'El nombre es obligatorio',
            'tipo_producto.required' => 'El tipo de requerimiento es obligatorio',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = CategoriasService::crear_categoria(
            nombre: (string) $request->input('nombre'),
            tipo_producto: TipoProducto::from($request->input('tipo_producto')),
            descripcion: $request->input('descripcion'),
            clasificacion_bien: TipoBien::from($request->input('clasificacion_bien')),
            para_transporte: (bool) $request->boolean('para_transporte'),
            control_por_odometro: (bool) $request->boolean('control_por_odometro'),
            control_por_horometro: (bool) $request->boolean('control_por_horometro'),
            control_por_vueltas: (bool) $request->boolean('control_por_vueltas'),
            es_consumible: (bool) $request->boolean('es_consumible'),
            para_cocina: (bool) $request->boolean('para_cocina'),
            para_mina: (bool) $request->boolean('para_mina'),
            es_auditable: (bool) $request->boolean('es_auditable'),
        );

        return response()->json($result);
    }
}
