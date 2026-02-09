<?php

namespace App\Modules\Menu\Presentation\Controllers;

use App\Modules\Menu\Services\MenuService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controlador para el sistema de módulos y menú.
 */
class MenuController extends Controller
{
    public function __construct(
        private MenuService $menuService
    ) {}

    /**
     * Obtener menu de navegacion en base al rol del usuario autenticado.
     */
    public function get_menu_navegacion(Request $request): JsonResponse
    {
        $authUser = $request->input('auth_user');

        if (! $authUser || ! isset($authUser->id_rol)) {
            return ApiResponse::unauthorized('No autorizado');
        }

        $result = $this->menuService->obtenerMenuPorRol($authUser->id_rol);

        if (! $result['success']) {
            return ApiResponse::error($result['message']);
        }

        return ApiResponse::success($result['data']);
    }
}
