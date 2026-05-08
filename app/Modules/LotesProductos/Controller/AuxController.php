<?php

namespace App\Modules\LotesProductos\Controller;

use App\Shared\Responses\ApiResponse;
use App\Modules\LotesProductos\Service\AuxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuxController extends Controller
{
    public function get_almacenes(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $result = AuxService::get_almacenes($authUser->id_empleado);
        return response()->json($result);
    }

    public function get_unidades_medida(): JsonResponse
    {
        $result = AuxService::get_unidades_medida();
        return response()->json($result);
    }



    public function get_productos(Request $request): JsonResponse
    {
        $result = AuxService::get_productos();

        return response()->json($result);
    }
}
