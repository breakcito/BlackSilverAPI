<?php

namespace App\Modules\Empresa\Presentation\Controllers;

use App\Modules\Empresa\Application\Dtos\CrearAreaRequest;
use App\Modules\Empresa\Application\Usecases\CrearAreaUseCase;
use App\Modules\Empresa\Application\Usecases\ListarAreasUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controlador para gestión de áreas.
 */
class AreaController extends Controller
{
    public function __construct(
        private ListarAreasUseCase $listarAreasUseCase,
        private CrearAreaUseCase $crearAreaUseCase,
    ) {}

    /**
     * Listar áreas activas.
     */
    public function index(): JsonResponse
    {
        $areas = $this->listarAreasUseCase->execute();

        return response()->json($areas);
    }

    /**
     * Crear una nueva área.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:64',
            'descripcion' => 'nullable|string|max:256',
        ]);

        $dto = CrearAreaRequest::fromArray($request->only(['nombre', 'descripcion']));
        $area = $this->crearAreaUseCase->execute($dto);

        return response()->json($area, 201);
    }
}
