<?php

namespace App\Modules\Usuarios\Application\Dtos;

/**
 * DTO para la solicitud de login.
 */
readonly class LoginRequest
{
    public function __construct(
        public string $username,
        public string $password,
    ) {}

    /**
     * Crear desde array de datos.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            username: $data['username'] ?? '',
            password: $data['password'] ?? '',
        );
    }
}
