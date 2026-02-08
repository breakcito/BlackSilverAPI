<?php

namespace App\Modules\Empleados\Application\Dtos;

/**
 * DTO para crear un empleado.
 */
readonly class CrearEmpleadoRequest
{
    public function __construct(
        public int $idAreaEmpresa,
        public int $idCargo,
        public string $nombre,
        public string $apellido,
        public ?string $dni = null,
        public ?string $ruc = null,
        public ?string $carnetExtranjeria = null,
        public ?string $pasaporte = null,
        public ?string $fechaNacimiento = null,
    ) {}

    /**
     * Crear desde array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            idAreaEmpresa: $data['id_area_empresa'],
            idCargo: $data['id_cargo'],
            nombre: $data['nombre'],
            apellido: $data['apellido'],
            dni: $data['dni'] ?? null,
            ruc: $data['ruc'] ?? null,
            carnetExtranjeria: $data['carnet_extranjeria'] ?? null,
            pasaporte: $data['pasaporte'] ?? null,
            fechaNacimiento: $data['fecha_nacimiento'] ?? null,
        );
    }
}
