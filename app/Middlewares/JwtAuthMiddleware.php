<?php

namespace App\Http\Middleware;

use App\Modules\Usuarios\Services\UsuarioService;
use App\Shared\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

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
            // Debug: Capturar el header Authorization completo
            $authHeader = $request->header('Authorization');

            // Verificar si el header existe
            if (! $authHeader) {
                return response()->json(ApiResponse::error('Token no proporcionado. Header Authorization faltante.'), 401);
            }

            // Verificar el formato del header
            if (! str_starts_with($authHeader, 'Bearer ')) {
                return response()->json(ApiResponse::error('Formato de token inválido. Debe ser: Bearer {token}'), 401);
            }

            // Extraer el token (sin el prefijo "Bearer ")
            $tokenString = substr($authHeader, 7);

            // Verificar si el token está vacío o tiene caracteres extraños
            if (empty($tokenString) || strlen($tokenString) !== strlen(trim($tokenString))) {
                return response()->json(ApiResponse::error('Token contiene espacios o está vacío'), 401);
            }

            // Intentar parsear el token\
            $token = JWTAuth::parseToken();
            $payload = $token->getPayload();
            $id_usuario = $payload->get('sub');

            if (! $id_usuario) {
                return response()->json(ApiResponse::error('Token inválido: falta el identificador de usuario'), 401);
            }

            $result = $this->usuarioService->validarUsuarioJWT($id_usuario);

            if (! $result['success']) {
                return response()->json($result, 401);
            }

            // Usar attributes en lugar de merge para garantizar que el controlador pueda acceder
            $request->attributes->set('auth_user', $result['data']);

            return $next($request);
        } catch (\PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(ApiResponse::error('Token expirado'), 401);
        } catch (\PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(ApiResponse::error('Token inválido: '.$e->getMessage()), 401);
        } catch (\PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(ApiResponse::error('Error JWT: '.$e->getMessage()), 401);
        } catch (\Exception $e) {
            return response()->json(ApiResponse::error('Error de autenticación: '.$e->getMessage()), 401);
        }
    }
}
