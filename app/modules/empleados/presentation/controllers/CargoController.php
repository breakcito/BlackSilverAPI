<?php

namespace App\Modules\Empleados\Presentation\Controllers;

use App\Modules\Empleados\Application\Dtos\CrearCargoRequest;
use App\Modules\Empleados\Application\Usecases\CrearCargoUseCase;
use App\Modules\Empleados\Application\Usecases\ListarCargosUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controlador para gestión de cargos.
 */
class CargoController extends Controller
{
    public function __construct(
        private ListarCargosUseCase $listarCargosUseCase,
        private CrearCargoUseCase $crearCargoUseCase,
    ) {}

    /**
     * Listar cargos activos.
     */
    public function index(): JsonResponse
    {
        $cargos = $this->listarCargosUseCase->execute();

        return response()->json($cargos);
    }

    /**
     * Crear un nuevo cargo.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:64',
            'descripcion' => 'nullable|string|max:256',
        ]);

        $dto = CrearCargoRequest::fromArray($request->only(['nombre', 'descripcion']));
        $cargo = $this->crearCargoUseCase->execute($dto);

        return response()->json($cargo, 201);
    }
}
