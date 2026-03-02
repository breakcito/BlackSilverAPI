<?php

namespace App\Controllers;

use App\Services\MenuNavegacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MenuNavegacionController extends Controller
{
    public function __construct(
        private MenuNavegacionService $menuService
    ) {}

    public function get_menu_navegacion_by_rol(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $result = $this->menuService->get_menu_navegacion_by_rol($authUser->id_rol);

        return response()->json($result);
    }
}
