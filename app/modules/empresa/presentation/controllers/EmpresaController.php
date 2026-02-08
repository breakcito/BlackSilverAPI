<?php

namespace App\Modules\Empresa\Presentation\Controllers;

use App\Modules\Empresa\Application\Usecases\ListarEmpresasUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Controlador para gestión de empresas.
 */
class EmpresaController extends Controller
{
    public function __construct(
        private ListarEmpresasUseCase $listarEmpresasUseCase,
    ) {}

    /**
     * Listar empresas.
     */
    public function index(): JsonResponse
    {
        $empresas = $this->listarEmpresasUseCase->execute();

        return response()->json($empresas);
    }
}
