<?php

namespace App\Views\Proveedores\Services;

use App\Views\Proveedores\Data\ProveedoresData;

class ProveedoresService
{
    protected ProveedoresData $data;

    public function __construct(ProveedoresData $data)
    {
        $this->data = $data;
    }

    public function get_proveedores(): array
    {
        return $this->data->get_proveedores();
    }

    public function crear_proveedor(string $tipoEntidad, ?string $dni, ?string $ruc, string $razonSocial, ?string $direccion, ?string $telefono, ?string $correo): int
    {
        return $this->data->crear_proveedor($tipoEntidad, $dni, $ruc, $razonSocial, $direccion, $telefono, $correo);
    }
}
