<?php

namespace App\Modules\Empleados\Application\Dtos;

/**
 * DTO para crear un cargo.
 */
readonly class CrearCargoRequest
{
    public function __construct(
        public string $nombre,
        public ?string $descripcion = null,
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
        );
    }
}
