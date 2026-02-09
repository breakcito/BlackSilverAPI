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
        $result = $this->concesionService->crear_concesion($request->id_empresa, $request->nombre);
        return response()->json($result);
    }
}
