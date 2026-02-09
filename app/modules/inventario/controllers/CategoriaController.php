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

    public function index(Request $request): JsonResponse
    {
        $tipo_requerimiento = $request->query('tipo_requerimiento');
        $result = $this->categoriaService->get_categorias($tipo_requerimiento);
        return response()->json($result);
    }

    public function show(int $id): JsonResponse
    {
        $result = $this->categoriaService->get_categoria_by_id($id);
        return response()->json($result);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'tipo_requerimiento' => ['required', new Enum(TipoRequerimiento::class)],
            'clasificacion_bien' => [
                'nullable', 
                Rule::requiredIf(fn() => $request->tipo_requerimiento === TipoRequerimiento::Bien->value),
                Rule::prohibitedIf(fn() => $request->tipo_requerimiento === TipoRequerimiento::Servicio->value),
                new Enum(ClasificacionBien::class)
            ],
        ], [
            'nombre.required' => 'El nombre es requerido',
            'tipo_requerimiento.required' => 'El tipo de requerimiento es requerido',
            'tipo_requerimiento.Illuminate\Validation\Rules\Enum' => 'El tipo de requerimiento no es válido',
            'clasificacion_bien.Illuminate\Validation\Rules\Enum' => 'La clasificación del bien no es válida',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = $this->categoriaService->crear_categoria($request->all());
        return response()->json($result);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'tipo_requerimiento' => ['required', new Enum(TipoRequerimiento::class)],
            'clasificacion_bien' => [
                'nullable', 
                Rule::requiredIf(fn() => $request->tipo_requerimiento === TipoRequerimiento::Bien->value),
                Rule::prohibitedIf(fn() => $request->tipo_requerimiento === TipoRequerimiento::Servicio->value),
                new Enum(ClasificacionBien::class)
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = $this->categoriaService->update_categoria($id, $request->all());
        return response()->json($result);
    }

    public function destroy(int $id): JsonResponse
    {
        $result = $this->categoriaService->delete_categoria($id);
        return response()->json($result);
    }
}
