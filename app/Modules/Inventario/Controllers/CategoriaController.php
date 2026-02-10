<?php

namespace App\Modules\Inventario\Controllers;

use App\Modules\Inventario\Services\CategoriaService;
use App\Shared\Enums\ClasificacionBien;
use App\Shared\Enums\TipoRequerimiento;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rule;

class CategoriaController extends Controller
{
    public function __construct(
        private CategoriaService $categoriaService
    ) {}

    public function get_categorias(Request $request): JsonResponse
    {
        $tipo_requerimiento = $request->query('tipo_requerimiento');
        $result = $this->categoriaService->get_categorias($tipo_requerimiento);
        return response()->json($result);
    }

    public function get_categoria_by_id(Request $request): JsonResponse
    {
        $id = $request->query('id');
        if (!$id) {
            return response()->json(ApiResponse::error('El id es requerido'), 400);
        }
        $result = $this->categoriaService->get_categoria_by_id((int)$id);
        return response()->json($result);
    }

    public function crear_categoria(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'tipo_requerimiento' => ['required', new Enum(TipoRequerimiento::class)],
            'clasificacion_bien' => ['nullable', new Enum(ClasificacionBien::class)],
        ], [
            'nombre.required' => 'El nombre es requerido',
            'tipo_requerimiento.required' => 'El tipo de requerimiento es requerido',
            'tipo_requerimiento.Illuminate\Validation\Rules\Enum' => 'El tipo de requerimiento no es válido',
            'clasificacion_bien.Illuminate\Validation\Rules\Enum' => 'La clasificación del bien no es válida',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = $this->categoriaService->crear_categoria($validator->validated());
        return response()->json($result);
    }

    public function update_categoria(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:categoria,id',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'tipo_requerimiento' => ['required', new Enum(TipoRequerimiento::class)],
            'clasificacion_bien' => ['nullable', new Enum(ClasificacionBien::class)],
        ], [
            'id.required' => 'El id es requerido',
            'id.exists' => 'La categoría no existe',
            // ...
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = $this->categoriaService->update_categoria($request->id, $validator->validated());
        return response()->json($result);
    }

    public function delete_categoria(Request $request): JsonResponse
    {
        $id = $request->input('id');
        if (!$id) {
            return response()->json(ApiResponse::error('El id es requerido'), 400);
        }
        $result = $this->categoriaService->delete_categoria((int)$id);
        return response()->json($result);
    }
}
