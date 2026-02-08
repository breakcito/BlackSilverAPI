<?php

namespace App\Modules\Usuarios\Presentation\Controllers;

use App\Modules\Usuarios\Presentation\Requests\LoginRequest;
use App\Modules\Usuarios\Presentation\Requests\StoreUsuarioRequest;
use App\Modules\Usuarios\Services\UsuarioService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Controlador para gestión de usuarios.
 */
class UsuarioController extends Controller
{
    public function __construct(
        private UsuarioService $usuarioService
    ) {}

    /**
     * Listar usuarios.
     */
    public function index(): JsonResponse
    {
        $result = $this->usuarioService->obtenerUsuarios();

        if (! $result['success']) {
            return ApiResponse::error($result['message']);
        }

        return ApiResponse::success($result['data']);
    }

    /**
     * Registrar un nuevo usuario con su empleado.
     */
    public function store(StoreUsuarioRequest $request): JsonResponse
    {
        $result = $this->usuarioService->registrarUsuario(
            $request->validated('id_cargo_empresa'),
            $request->validated('id_rol'),
            $request->validated('nombre'),
            $request->validated('apellido'),
            $request->validated('dni'),
            $request->validated('ruc'),
            $request->validated('carnet_extranjeria'),
            $request->validated('pasaporte'),
            $request->validated('fecha_nacimiento'),
            $request->validated('username'),
            $request->validated('password'),
            $request->file('foto')
        );

        if (! $result['success']) {
            return ApiResponse::error($result['message']);
        }

        return ApiResponse::created($result['data'], $result['message']);
    }

    /**
     * Iniciar sesión.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->usuarioService->login(
            $request->validated('username'),
            $request->validated('password')
        );

        if (! $result['success']) {
            return ApiResponse::unauthorized($result['message']);
        }

        return ApiResponse::success($result['data'], $result['message']);
    }
}
