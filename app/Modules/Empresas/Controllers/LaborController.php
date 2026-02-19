<?php

namespace App\Modules\Empresas\Controllers;

use App\Modules\Empresas\Services\LaborService;
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
        $id_mina = $request->query('id_mina');
        $result = $this->laborService->get_labores($id_mina ? (int)$id_mina : null);
        return response()->json($result);
    }

    public function get_tipos_labor(Request $request): JsonResponse
    {
        $tipos = \App\Modules\Empresas\Models\TipoLabor::get_tipos_labor();
        return response()->json(ApiResponse::success($tipos));
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
            'id_mina'            => 'required|integer',
            'id_empresa'         => 'required|integer',
            'id_tipo_labor'      => 'required|integer',
            'codigo_correlativo' => 'required|string|max:32',
            'nombre'             => 'required|string|max:128',
            'descripcion'        => 'nullable|string',
            'tipo_sostenimiento' => ['required', new Enum(TipoSostenimiento::class)]
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $result = $this->laborService->crear_labor(
            $request->id_empresa,
            $request->id_mina,
            $request->id_tipo_labor,
            $request->codigo_correlativo,
            $request->nombre,
            $request->descripcion,
            $request->tipo_sostenimiento
        );
        return response()->json($result);
    }

    public function update_labor(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_labor'           => 'required|integer',
            'id_mina'            => 'required|integer',
            'id_empresa'         => 'required|integer',
            'id_tipo_labor'      => 'required|integer',
            'codigo_correlativo' => 'required|string|max:32',
            'nombre'             => 'required|string|max:128',
            'descripcion'        => 'nullable|string',
            'tipo_sostenimiento' => ['required', new Enum(TipoSostenimiento::class)]
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $result = $this->laborService->update_labor(
            $request->id_labor,
            $request->id_empresa,
            $request->id_mina,
            $request->id_tipo_labor,
            $request->codigo_correlativo,
            $request->nombre,
            $request->descripcion,
            $request->tipo_sostenimiento
        );

        return response()->json($result);
    }

    public function delete_labor(Request $request): JsonResponse
    {
        $id = $request->query('id_labor'); // Cambiado a query 'id_labor' para consistencia
        if (!$id) {
            return response()->json(ApiResponse::error('El id es requerido'), 400);
        }
        $result = $this->laborService->delete_labor((int)$id);
        return response()->json($result);
    }

    // --- RESPONSABLES DE LABOR ---

    // --- RESPONSABLES DE LABOR ---

    public function asignar_responsable_labor(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_labor'     => 'required|integer',
            'id_usuario'   => 'required|integer',
            'fecha_inicio' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $result = $this->laborService->asignar_responsable_labor(
            $request->id_labor,
            $request->id_usuario,
            $request->fecha_inicio
        );
        return response()->json($result);
    }

    public function get_responsables_labor(Request $request): JsonResponse
    {
        $id_labor = $request->input('id_labor'); // Se pasa en el body por POST (o query si prefieres, pero seguiste POST en las otras listas filtradas)
        if (!$id_labor) {
            return response()->json(ApiResponse::error('El id_labor es requerido'), 400);
        }

        $result = $this->laborService->get_responsables_labor((int)$id_labor);
        return response()->json($result);
    }
}
