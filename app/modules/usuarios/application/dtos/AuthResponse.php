<?php

namespace App\Modules\Usuarios\Application\Dtos;

/**
 * DTO para la respuesta de autenticación.
 */
readonly class AuthResponse
{
    public function __construct(
        public string $accessToken,
        public string $tokenType,
        public int $expiresIn,
        public UsuarioDTO $usuario,
    ) {}

    /**
     * Convertir a array para respuesta JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'token_type' => $this->tokenType,
            'expires_in' => $this->expiresIn,
            'usuario' => $this->usuario->toArray(),
        ];
    }
}
