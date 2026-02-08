<?php

namespace App\Modules\Usuarios\Presentation\Controllers;

use App\Modules\Usuarios\Application\Dtos\CrearUsuarioRequest;
use App\Modules\Usuarios\Application\Usecases\ActualizarPasswordUseCase;
use App\Modules\Usuarios\Application\Usecases\CrearUsuarioUseCase;
use App\Modules\Usuarios\Application\Usecases\ListarUsuariosUseCase;
use App\Modules\Usuarios\Infraestructure\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controlador para gestión de usuarios.
 */
class UsuarioController extends Controller
{
    public function __construct(
        private ListarUsuariosUseCase $listarUsuariosUseCase,
        private CrearUsuarioUseCase $crearUsuarioUseCase,
        private ActualizarPasswordUseCase $actualizarPasswordUseCase,
    ) {}

    /**
     * Listar usuarios.
     */
    public function index(Request $request): JsonResponse
    {
        $idEmpresa = $request->query('id_empresa');
        $usuarios = $this->listarUsuariosUseCase->execute(
            $idEmpresa ? (int) $idEmpresa : null
        );

        return response()->json($usuarios);
    }

    /**
     * Crear un nuevo usuario.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_rol' => 'required|integer|exists:rol,id',
            'id_empleado' => 'required|integer|exists:empleado,id',
            'username' => 'required|string|max:64|unique:usuario,username',
            'password' => 'required|string|min:8',
        ]);

        try {
            $dto = CrearUsuarioRequest::fromArray($request->all());
            $usuario = $this->crearUsuarioUseCase->execute($dto);

            return response()->json($usuario->load(['rol', 'empleado']), 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Actualizar contraseña del usuario autenticado.
     */
    public function actualizarPassword(Request $request): JsonResponse
    {
        $request->validate([
            'password_actual' => 'required|string',
            'password_nuevo' => 'required|string|min:8|confirmed',
        ]);

        /** @var Usuario $usuario */
        $usuario = auth('api')->user();

        try {
            $this->actualizarPasswordUseCase->execute(
                $usuario,
                $request->input('password_actual'),
                $request->input('password_nuevo')
            );

            return response()->json(['message' => 'Contraseña actualizada correctamente']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
