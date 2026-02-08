<?php

namespace App\Modules\Usuarios\Application\Dtos;

use App\Modules\Usuarios\Infraestructure\Models\Usuario;

/**
 * DTO para representar datos de usuario.
 */
readonly class UsuarioDTO
{
    public function __construct(
        public int $id,
        public string $username,
        public int $idRol,
        public ?int $idEmpleado,
        public string $estado,
    ) {}

    /**
     * Crear desde modelo Usuario.
     */
    public static function fromModel(Usuario $usuario): self
    {
        return new self(
            id: $usuario->id,
            username: $usuario->username,
            idRol: $usuario->id_rol,
            idEmpleado: $usuario->id_empleado,
            estado: $usuario->estado->value,
        );
    }

    /**
     * Convertir a array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'id_rol' => $this->idRol,
            'id_empleado' => $this->idEmpleado,
            'estado' => $this->estado,
        ];
    }
}
