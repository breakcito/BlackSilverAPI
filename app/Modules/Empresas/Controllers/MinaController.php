<?php

namespace App\Modules\Empresas\Controllers;

use App\Modules\Empresas\Services\MinaService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class MinaController extends Controller
{
    public function __construct(private MinaService $minaService) {}

    public function get_minas(Request $request): JsonResponse
    {
        $id_concesion = $request->query('id_concesion');
        $result = $this->minaService->get_minas($id_concesion ? (int)$id_concesion : null);
        return response()->json($result);
    }

    public function crear_mina(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_concesion' => 'required|integer',
            'nombre'       => 'required|string|max:128',
            'descripcion'  => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $result = $this->minaService->crear_mina(
            $request->id_concesion,
            $request->nombre,
            $request->descripcion
        );

        return response()->json($result);
    }

    public function update_mina(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_mina'      => 'required|integer',
            'id_concesion' => 'required|integer',
            'nombre'       => 'required|string|max:128',
            'descripcion'  => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $result = $this->minaService->update_mina(
            $request->id_mina,
            $request->id_concesion,
            $request->nombre,
            $request->descripcion
        );

        return response()->json($result);
    }

    public function delete_mina(Request $request): JsonResponse
    {
        $id = $request->query('id_mina');
        if (!$id) return response()->json(ApiResponse::error('ID requerido'), 400);

        $result = $this->minaService->delete_mina((int)$id);
        return response()->json($result);
    }

    // --- EMPRESA MINA ---

    public function asignar_empresa_mina(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_mina'    => 'required|integer',
            'id_empresa' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $result = $this->minaService->asignar_empresa_mina($request->id_mina, $request->id_empresa);
        return response()->json($result);
    }

    public function desasignar_empresa_mina(Request $request): JsonResponse
    {
        $id = $request->query('id_asignacion');
        if (!$id) return response()->json(ApiResponse::error('ID asignacion requerido'), 400);

        $result = $this->minaService->desasignar_empresa_mina((int)$id);
        return response()->json($result);
    }

    public function get_empresas_mina(Request $request): JsonResponse
    {
        $id = $request->query('id_mina');
        if (!$id) return response()->json(ApiResponse::error('ID mina requerido'), 400);

        $result = $this->minaService->get_empresas_mina((int)$id);
        return response()->json($result);
    }
}
