<?php

namespace App\Modules\Empleados\Presentation\Controllers;

use App\Modules\Empleados\Application\Dtos\CrearEmpleadoRequest;
use App\Modules\Empleados\Application\Usecases\CrearEmpleadoUseCase;
use App\Modules\Empleados\Application\Usecases\ListarEmpleadosUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controlador para gestión de empleados.
 */
class EmpleadoController extends Controller
{
    public function __construct(
        private ListarEmpleadosUseCase $listarEmpleadosUseCase,
        private CrearEmpleadoUseCase $crearEmpleadoUseCase,
    ) {}

    /**
     * Listar empleados.
     */
    public function index(Request $request): JsonResponse
    {
        $idEmpresa = $request->query('id_empresa');
        $empleados = $this->listarEmpleadosUseCase->execute(
            $idEmpresa ? (int) $idEmpresa : null
        );

        return response()->json($empleados);
    }

    /**
     * Crear un nuevo empleado.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_area_empresa' => 'required|integer|exists:area_empresa,id',
            'id_cargo' => 'required|integer|exists:cargo,id',
            'nombre' => 'required|string|max:128',
            'apellido' => 'required|string|max:128',
            'dni' => 'nullable|string|size:8|unique:empleado,dni',
            'ruc' => 'nullable|string|size:11|unique:empleado,ruc',
            'carnet_extranjeria' => 'nullable|string|max:64',
            'pasaporte' => 'nullable|string|max:64',
            'fecha_nacimiento' => 'nullable|date',
        ]);

        $dto = CrearEmpleadoRequest::fromArray($request->all());
        $empleado = $this->crearEmpleadoUseCase->execute($dto);

        return response()->json($empleado->load(['cargo', 'areaEmpresa']), 201);
    }
}
