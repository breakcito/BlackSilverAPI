<?php

namespace App\Modules\Sistema\Presentation\Controllers;

use App\Modules\Sistema\Application\Usecases\ListarModulosUseCase;
use App\Modules\Sistema\Application\Usecases\ObtenerMenuUsuarioUseCase;
use App\Modules\Usuarios\Infraestructure\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Controlador para el sistema de módulos y menú.
 */
class SistemaController extends Controller
{
    public function __construct(
        private ListarModulosUseCase $listarModulosUseCase,
        private ObtenerMenuUsuarioUseCase $obtenerMenuUsuarioUseCase,
    ) {}

    /**
     * Listar módulos con submódulos y secciones.
     */
    public function modulos(): JsonResponse
    {
        $modulos = $this->listarModulosUseCase->execute();

        return response()->json($modulos);
    }

    /**
     * Obtener menú de navegación del usuario autenticado.
     */
    public function menu(): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = auth('api')->user();
        $menu = $this->obtenerMenuUsuarioUseCase->execute($usuario);

        return response()->json($menu);
    }
}
