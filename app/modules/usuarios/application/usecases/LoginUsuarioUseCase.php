<?php

namespace App\Modules\Usuarios\Application\Usecases;

use App\Enums\EstadoBase;
use App\Modules\Usuarios\Application\Dtos\AuthResponse;
use App\Modules\Usuarios\Application\Dtos\LoginRequest;
use App\Modules\Usuarios\Application\Dtos\UsuarioDTO;
use App\Modules\Usuarios\Infraestructure\Models\Usuario;

/**
 * Caso de uso para autenticación de usuarios.
 */
class LoginUsuarioUseCase
{
    /**
     * Ejecutar el caso de uso de login.
     *
     * @throws \InvalidArgumentException Si las credenciales son inválidas
     * @throws \RuntimeException Si el usuario está inactivo
     */
    public function execute(LoginRequest $request): AuthResponse
    {
        $credentials = [
            'username' => $request->username,
            'password' => $request->password,
        ];

        /** @var string|false $token */
        $token = auth('api')->attempt($credentials);

        if (! $token) {
            throw new \InvalidArgumentException('Credenciales inválidas');
        }

        /** @var Usuario $usuario */
        $usuario = auth('api')->user();

        if ($usuario->estado !== EstadoBase::Activo) {
            auth('api')->logout();

            throw new \RuntimeException('Usuario inactivo o eliminado');
        }

        return new AuthResponse(
            accessToken: $token,
            tokenType: 'bearer',
            expiresIn: (int) config('jwt.ttl') * 60,
            usuario: UsuarioDTO::fromModel($usuario),
        );
    }
}
