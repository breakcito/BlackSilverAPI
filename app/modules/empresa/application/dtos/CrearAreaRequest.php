<?php

namespace App\Modules\Empresa\Application\Dtos;

/**
 * DTO para crear un área.
 */
readonly class CrearAreaRequest
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
