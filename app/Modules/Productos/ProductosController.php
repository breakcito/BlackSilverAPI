<?php

namespace App\Modules\Productos;

use App\Shared\Enums\_Generic\Periodo;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;

class ProductosController
{
    /**
     * Listar productos
     */
    public function get_productos(Request $request): JsonResponse
    {
        $result = ProductosService::get_productos();

        return response()->json($result);
    }

    /**
     * Obtener unidades de medida base
     */
    public function get_unidades_medida(Request $request): JsonResponse
    {
        $result = ProductosService::get_unidades_medida();

        return response()->json($result);
    }

    /**
     * Obtener categorías internas del módulo de productos
     */
    public function get_categorias(Request $request): JsonResponse
    {
        $result = ProductosService::get_categorias();

        return response()->json($result);
    }

    /**
     * Crear un nuevo producto
     */
    public function crear_producto(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_categoria' => 'required|integer',
            'id_unidad_medida_base' => 'required|integer',
            'nombre' => 'required|string|max:128',
            'es_fiscalizado' => 'required|boolean',
            'es_perecible' => 'required|boolean',
            'stock_minimo' => 'nullable|numeric|min:0',
            'tiempo_espera_vencimiento' => 'nullable|integer|min:0',
            'periodo_espera_vencimiento' => ['nullable', new Enum(Periodo::class)],
        ], [
            'id_categoria.required' => 'La categoría es requerida',
            'id_unidad_medida_base.required' => 'La unidad de medida es requerida',
            'nombre.required' => 'El nombre es requerido',
            'es_fiscalizado.required' => 'Debe indicar si es fiscalizado',
            'es_perecible.required' => 'Debe indicar si es perecible',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = ProductosService::crear_producto(
            id_categoria: $request->integer('id_categoria'),
            id_unidad_medida_base: $request->integer('id_unidad_medida_base'),
            nombre: $request->string('nombre'),
            es_fiscalizado: $request->boolean('es_fiscalizado'),
            es_perecible: $request->boolean('es_perecible'),
            stock_minimo: (float) ($request->input('stock_minimo') ?? 0),
            tiempo_espera_vencimiento: $request->input('tiempo_espera_vencimiento') ? (int) $request->input('tiempo_espera_vencimiento') : null,
            periodo_espera_vencimiento: $request->input('periodo_espera_vencimiento')
        );

        return response()->json($result);
    }
}
