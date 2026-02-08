<?php

namespace App\Modules\Usuarios\Presentation\Controllers;

use App\Modules\Usuarios\Application\Dtos\LoginRequest;
use App\Modules\Usuarios\Application\Usecases\LoginUsuarioUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controlador para autenticación de usuarios.
 */
class AuthController extends Controller
{
    public function __construct(
        private LoginUsuarioUseCase $loginUseCase,
    ) {}

    /**
     * Iniciar sesión y obtener token JWT.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            $loginRequest = LoginRequest::fromArray($request->only(['username', 'password']));
            $response = $this->loginUseCase->execute($loginRequest);

            return response()->json($response->toArray());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * Cerrar sesión (invalidar token).
     */
    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    /**
     * Refrescar token JWT.
     */
    public function refresh(): JsonResponse
    {
        /** @var string $token */
        $token = auth('api')->refresh();

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => (int) config('jwt.ttl') * 60,
        ]);
    }

    /**
     * Obtener usuario autenticado.
     */
    public function me(): JsonResponse
    {
        $usuario = auth('api')->user();

        return response()->json($usuario);
    }
}
