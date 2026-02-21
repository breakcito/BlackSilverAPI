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
            'nombre'             => 'required|string|max:128',
            'descripcion'        => 'nullable|string',
            'tipo_sostenimiento' => ['required', new Enum(TipoSostenimiento::class)],
            'veta'               => 'nullable|string|max:128',
            'ancho'              => 'nullable|numeric',
            'alto'               => 'nullable|numeric',
            'nivel'              => 'nullable|string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $result = $this->laborService->crear_labor(
            $request->id_empresa,
            $request->id_mina,
            $request->id_tipo_labor,
            $request->nombre,
            $request->descripcion,
            $request->tipo_sostenimiento,
            $request->veta,
            $request->ancho,
            $request->alto,
            $request->nivel
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
            'nombre'             => 'required|string|max:128',
            'descripcion'        => 'nullable|string',
            'tipo_sostenimiento' => ['required', new Enum(TipoSostenimiento::class)],
            'veta'               => 'nullable|string|max:128',
            'ancho'              => 'nullable|numeric',
            'alto'               => 'nullable|numeric',
            'nivel'              => 'nullable|string|max:64',
            'fecha_fin'          => 'nullable|date',
            'estado'             => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $result = $this->laborService->update_labor(
            $request->id_labor,
            $request->id_empresa,
            $request->id_mina,
            $request->id_tipo_labor,
            $request->nombre,
            $request->descripcion,
            $request->tipo_sostenimiento,
            $request->veta,
            $request->ancho,
            $request->alto,
            $request->nivel,
            $request->fecha_fin,
            $request->estado
        );

        return response()->json($result);
    }

    public function delete_labor(Request $request): JsonResponse
    {
        $id = $request->query('id_labor');
        if (!$id) {
            return response()->json(ApiResponse::error('El id es requerido'), 400);
        }
        $result = $this->laborService->delete_labor((int)$id);
        return response()->json($result);
    }
}
