<?php

namespace App\Modules\Menu\Controllers;

use App\Modules\Menu\Services\MenuService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MenuController extends Controller
{
    public function __construct(
        private MenuService $menuService
    ) {}

    public function get_menu_navegacion_by_rol(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        if (!$authUser || !isset($authUser->id_rol)) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $result = $this->menuService->get_menu_navegacion_by_rol($authUser->id_rol);

        return response()->json($result);
    }
}
