<?php

namespace App\Controllers;

use App\Services\ConcesionService;
use App\Shared\Enums\TipoMineral;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;

class ConcesionController extends Controller
{
    public function __construct(
        private ConcesionService $concesionService
    ) {}

    public function get_concesiones(Request $request): JsonResponse
    {
        $result = $this->concesionService->get_concesiones();

        return response()->json($result);
    }

    public function get_tipos_mineral(): JsonResponse
    {
        $tipos = array_column(TipoMineral::cases(), 'value');

        return response()->json(ApiResponse::success($tipos));
    }

    public function get_concesiones_by_session(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        if (! $authUser || ! isset($authUser->id_rol)) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $result = $this->concesionService->get_concesiones_by_usuario($authUser->id_usuario);

        return response()->json($result);
    }

    public function get_concesiones_by_empresa(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_empresa' => 'required|integer',
        ], [
            'id_empresa.required' => 'La empresa es requerida',
        ]);
        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $data = $validator->validated();
        $result = $this->concesionService->get_concesiones_by_empresa($data['id_empresa']);

        return response()->json($result);
    }

    public function crear_concesion(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:64',
            'codigo_concesion' => 'nullable|string|max:64',
            'codigo_reinfo' => 'nullable|string|max:64',
            'ubigeo' => 'nullable|string|max:128',
            'tipo_mineral' => ['required', new Enum(TipoMineral::class)],
        ], [
            'nombre.required' => 'El nombre es requerido',
            'tipo_mineral.required' => 'El tipo de mineral es obligatorio',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $data = $validator->validated();
        $result = $this->concesionService->crear_concesion(
            $data['nombre'],
            $data['codigo_concesion'] ?? null,
            $data['codigo_reinfo'] ?? null,
            $data['ubigeo'] ?? null,
            $data['tipo_mineral'] ?? null
        );

        return response()->json($result);
    }

    public function get_empresas_historial(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_concesion' => 'required|integer',
        ], [
            'id_concesion.required' => 'La concesión es requerida',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = $this->concesionService->get_empresas_historial($request->id_concesion);

        return response()->json($result);
    }

    public function asignar_empresa(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_concesion' => 'required|integer',
            'id_empresa' => 'required|integer',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
        ], [
            'id_concesion.required' => 'La concesión es requerida',
            'id_empresa.required' => 'La empresa es requerida',
            'fecha_inicio.required' => 'La fecha de inicio es requerida',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $data = $validator->validated();
        $result = $this->concesionService->asignar_empresa(
            $data['id_concesion'],
            $data['id_empresa'],
            $data['fecha_inicio'],
            $data['fecha_fin'] ?? null
        );

        return response()->json($result);
    }

    public function desasignar_empresa(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_asignacion' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = $this->concesionService->desasignar_empresa($request->id_asignacion);

        return response()->json($result);
    }

    public function update_concesion(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_concesion' => 'required|integer',
            'nombre' => 'required|string',
        ], [
            'id_concesion.required' => 'La concesion es requerida',
            'nombre.required' => 'El nombre es requerido',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $data = $validator->validated();
        $result = $this->concesionService->update_concesion($data['id_concesion'], $data['nombre']);

        return response()->json($result);
    }

    public function delete_concesion(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_concesion' => 'required|integer',
        ], [
            'id_concesion.required' => 'La concesion es requerida',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $data = $validator->validated();
        $result = $this->concesionService->delete_concesion($data['id_concesion']);

        return response()->json($result);
    }
}
