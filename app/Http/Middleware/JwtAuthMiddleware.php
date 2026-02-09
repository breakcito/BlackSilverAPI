<?php

namespace App\Http\Middleware;

use App\Modules\Usuarios\Services\UsuarioService;
use App\Shared\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para autenticación JWT.
 */
class JwtAuthMiddleware
{
    public function __construct(
        private UsuarioService $usuarioService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $token = JWTAuth::parseToken();
            $payload = $token->getPayload();
            $id_usuario = $payload->get('sub');

            if (!$id_usuario) {
                return response()->json(ApiResponse::error('Token inválido'));
            }

            $result = $this->usuarioService->validarUsuarioJWT($id_usuario);

            if (!$result['success']) {
                return response()->json($result);
            }

            $request->merge(['auth_user' => $result['data']]);

            return $next($request);
        } catch (\PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(ApiResponse::error('Token expirado'));
        } catch (\PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(ApiResponse::error('Token inválido'));
        } catch (\PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(ApiResponse::error('Token no proporcionado'));
        }
    }
}
