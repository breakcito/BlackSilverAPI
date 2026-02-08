<?php

namespace App\Modules\Roles\Presentation\Controllers;

use App\Modules\Roles\Application\Dtos\CrearRolRequest;
use App\Modules\Roles\Application\Usecases\CrearRolUseCase;
use App\Modules\Roles\Application\Usecases\ListarRolesUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controlador para gestión de roles.
 */
class RolController extends Controller
{
    public function __construct(
        private ListarRolesUseCase $listarRolesUseCase,
        private CrearRolUseCase $crearRolUseCase,
    ) {}

    /**
     * Listar roles con permisos.
     */
    public function index(): JsonResponse
    {
        $roles = $this->listarRolesUseCase->execute();

        return response()->json($roles);
    }

    /**
     * Crear un nuevo rol con secciones y permisos.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:64',
            'descripcion' => 'nullable|string|max:256',
            'secciones' => 'array',
            'secciones.*.id_seccion' => 'required|integer|exists:seccion,id',
            'secciones.*.acciones' => 'array',
            'secciones.*.acciones.*' => 'integer|exists:accion_sistema_seccion,id',
        ]);

        $dto = CrearRolRequest::fromArray($request->all());
        $rol = $this->crearRolUseCase->execute($dto);

        return response()->json($rol, 201);
    }
}
