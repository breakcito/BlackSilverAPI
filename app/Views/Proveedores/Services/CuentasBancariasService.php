<?php

namespace App\Views\Proveedores\Services;

use App\Views\Proveedores\Data\CuentasBancariasData;

class CuentasBancariasService
{
    protected CuentasBancariasData $data;

    public function __construct(CuentasBancariasData $data)
    {
        $this->data = $data;
    }

    public function get_cuentas_bancarias(int $idProveedor): array
    {
        return $this->data->get_cuentas_bancarias($idProveedor);
    }

    public function crear_cuenta_bancaria(int $idProveedor, int $idBanco, string $moneda, string $numeroCuenta, ?string $cci, int $esParaDetraccion): int
    {
        return $this->data->crear_cuenta_bancaria($idProveedor, $idBanco, $moneda, $numeroCuenta, $cci, $esParaDetraccion);
    }
}
