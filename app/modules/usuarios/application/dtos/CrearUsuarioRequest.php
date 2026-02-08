<?php

namespace App\Modules\Usuarios\Application\Dtos;

/**
 * DTO para crear un usuario.
 */
readonly class CrearUsuarioRequest
{
    public function __construct(
        public int $idRol,
        public int $idEmpleado,
        public string $username,
        public string $password,
    ) {}

    /**
     * Crear desde array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            idRol: $data['id_rol'],
            idEmpleado: $data['id_empleado'],
            username: $data['username'],
            password: $data['password'],
        );
    }
}
