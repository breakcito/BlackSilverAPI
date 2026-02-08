<?php

namespace App\Modules\Menu\Presentation\Controllers;

use App\Modules\Menu\Application\Usecases\ListarModulosUseCase;
use App\Modules\Menu\Application\Usecases\ObtenerMenuUsuarioUseCase;
use App\Modules\Usuarios\Infraestructure\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Controlador para el sistema de módulos y menú.
 */
class MenuController extends Controller
{
    public function __construct(
        private ListarModulosUseCase $listarModulosUseCase,
        private ObtenerMenuUsuarioUseCase $obtenerMenuUsuarioUseCase,
    ) {}

    /**
     * Obtener menu de navegacion en base al rol del usuario autenticado.
     */
    public function get_menu_navegacion(): JsonResponse
    {
        $menu = $this->obtenerMenuUsuarioUseCase->execute();

        return response()->json($menu);
    }
}
