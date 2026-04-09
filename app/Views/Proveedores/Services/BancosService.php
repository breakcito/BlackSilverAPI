<?php

namespace App\Views\Proveedores\Services;

use App\Views\Proveedores\Data\BancosData;

class BancosService
{
    protected BancosData $data;

    public function __construct(BancosData $data)
    {
        $this->data = $data;
    }

    public function get_bancos(): array
    {
        return $this->data->get_bancos();
    }

    public function crear_banco(string $nombre, string $abreviatura): int
    {
        return $this->data->crear_banco($nombre, $abreviatura);
    }
}
