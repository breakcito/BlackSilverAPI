<?php

namespace App\Modules\Empresas\Controllers;

use App\Modules\Empresas\Services\LaborService;
use App\Shared\Enums\TipoLabor;
use App\Shared\Enums\TipoSostenimiento;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;

class LaborController extends Controller
{
    public function __construct(
        private LaborService $laborService
    ) {}

    public function get_labores(Request $request): JsonResponse
    {
        $id_empresa_concesion = $request->query('id_empresa_concesion');
        $result = $this->laborService->get_labores($id_empresa_concesion);
        return response()->json($result);
    }

    public function get_labor_by_id(Request $request): JsonResponse
    {
        $id = $request->query('id');
        if (!$id) {
            return response()->json(ApiResponse::error('El id es requerido'), 400);
        }
        $result = $this->laborService->get_labor_by_id((int)$id);
        return response()->json($result);
    }

    public function crear_labor(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_empresa_concesion' => 'required|integer|exists:empresa_concesion,id',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'tipo_labor' => ['required', new Enum(TipoLabor::class)],
            'tipo_sostenimiento' => ['required', new Enum(TipoSostenimiento::class)],
        ], [
            'id_empresa_concesion.required' => 'La asignación de empresa-concesión es requerida',
            'id_empresa_concesion.exists' => 'La asignación de empresa-concesión no existe',
            'nombre.required' => 'El nombre es requerido',
            'tipo_labor.required' => 'El tipo de labor es requerido',
            'tipo_labor.Illuminate\Validation\Rules\Enum' => 'El tipo de labor no es válido',
            'tipo_sostenimiento.required' => 'El tipo de sostenimiento es requerido',
            'tipo_sostenimiento.Illuminate\Validation\Rules\Enum' => 'El tipo de sostenimiento no es válido',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $data = $validator->validated();

        $result = $this->laborService->crear_labor(
            $data['id_empresa_concesion'],
            $data['nombre'],
            $data['descripcion'] ?? null,
            $data['tipo_labor'],
            $data['tipo_sostenimiento']
        );
        return response()->json($result);
    }

    public function update_labor(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:labor,id',
            'id_empresa_concesion' => 'required|integer|exists:empresa_concesion,id',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'tipo_labor' => ['required', new Enum(TipoLabor::class)],
            'tipo_sostenimiento' => ['required', new Enum(TipoSostenimiento::class)],
        ], [
            'id.required' => 'El id es requerido',
            'id.exists' => 'La labor no existe',
            'id_empresa_concesion.required' => 'La asignación de empresa-concesión es requerida',
            'id_empresa_concesion.exists' => 'La asignación de empresa-concesión no existe',
            // ... existing messages
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $data = $validator->validated();

        $result = $this->laborService->update_labor(
            $request->id,
            $data['id_empresa_concesion'],
            $data['nombre'],
            $data['descripcion'] ?? null,
            $data['tipo_labor'],
            $data['tipo_sostenimiento']
        );
        return response()->json($result);
    }

    public function delete_labor(Request $request): JsonResponse
    {
        $id = $request->input('id');
        if (!$id) {
            return response()->json(ApiResponse::error('El id es requerido'), 400);
        }
        $result = $this->laborService->delete_labor((int)$id);
        return response()->json($result);
    }
}
