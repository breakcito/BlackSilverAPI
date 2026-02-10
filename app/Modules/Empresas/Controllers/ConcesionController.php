<?php

namespace App\Modules\Empresas\Controllers;

use App\Modules\Empresas\Services\ConcesionService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

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

    public function get_concesiones_by_empresa(Request $request): JsonResponse
    {
        $id_empresa = $request->query('id_empresa');
        if (!$id_empresa) {
            return response()->json(ApiResponse::error('El id_empresa es requerido'), 400);
        }
        $result = $this->concesionService->get_concesiones_by_empresa((int)$id_empresa);
        return response()->json($result);
    }

    public function crear_concesion(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_empresa' => 'required|integer',
            'nombre' => 'required|string',
        ], [
            'id_empresa.required' => 'La empresa es requerida',
            'nombre.required' => 'El nombre es requerido',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }
        $data = $validator->validated();
        $result = $this->concesionService->crear_concesion($data['id_empresa'], $data['nombre']);
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
        $result = $this->concesionService->update_concesion($request->id_concesion, $data['nombre']);
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

        $result = $this->concesionService->delete_concesion($request->id_concesion);
        return response()->json($result);
    }
}
