<?php

namespace App\Modules\Empresas\Controllers;

use App\Modules\Empresas\Services\EmpresaService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EmpresaController extends Controller
{
    public function __construct(
        private EmpresaService $empresaService
    ) {}

    public function get_empresas_by_session(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        if (!$authUser || !isset($authUser->id_rol)) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $result = $this->empresaService->get_empresas_by_usuario($authUser->id_usuario);

        return response()->json($result);
    }
}
