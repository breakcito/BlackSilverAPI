<?php

namespace App\Modules\Roles\Application\Dtos;

/**
 * DTO para crear un rol.
 */
readonly class CrearRolRequest
{
    /**
     * @param  array<int, array{id_seccion: int, acciones: array<int>}>  $secciones
     */
    public function __construct(
        public string $nombre,
        public ?string $descripcion = null,
        public array $secciones = [],
    ) {}

    /**
     * Crear desde array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            nombre: $data['nombre'],
            descripcion: $data['descripcion'] ?? null,
            secciones: $data['secciones'] ?? [],
        );
    }
}
