<?php

namespace App\Modules\Clientes\Services;

use App\Shared\Responses\ApiResponse;
use App\Modules\Clientes\Data\CuentasBancariasData;

class CuentasBancariasService
{
    public static function get_cuentas_bancarias(int $idCliente): array
    {
        $data = CuentasBancariasData::get_cuentas_bancarias($idCliente);
        return ApiResponse::success($data, "Cuentas bancarias obtenidas correctamente");
    }

    public static function crear_cuenta_bancaria(
        int $idCliente,
        int $idBanco,
        string $moneda,
        string $numeroCuenta,
        ?string $cci,
        int $esParaDetraccion
    ): array {
        $existe = CuentasBancariasData::existe_cuenta_bancaria($idCliente, $idBanco, $numeroCuenta);

        if ($existe) {
            return ApiResponse::error("Esta cuenta bancaria ya está registrada para este cliente");
        }

        $id = CuentasBancariasData::crear_cuenta_bancaria(
            $idCliente,
            $idBanco,
            $moneda,
            $numeroCuenta,
            $cci,
            $esParaDetraccion
        );
        $nuevaCuenta = CuentasBancariasData::get_cuenta_bancaria_by_id($id);
        return ApiResponse::success($nuevaCuenta, "Cuenta bancaria registrada correctamente");
    }

    public static function actualizar_cuenta_bancaria(
        int $idCuentaBancaria,
        int $idBanco,
        string $moneda,
        string $numeroCuenta,
        ?string $cci,
        int $esParaDetraccion
    ): array {
        $id_cliente = CuentasBancariasData::get_cliente_id_by_cuenta($idCuentaBancaria);

        if ($id_cliente === null) {
            return ApiResponse::error("La cuenta bancaria no existe");
        }

        $existe = CuentasBancariasData::existe_cuenta_bancaria(
            $id_cliente,
            $idBanco,
            $numeroCuenta,
            excluir_id: $idCuentaBancaria
        );

        if ($existe) {
            return ApiResponse::error("Esta cuenta bancaria ya está registrada para este cliente");
        }

        CuentasBancariasData::actualizar_cuenta_bancaria(
            $idCuentaBancaria,
            $idBanco,
            $moneda,
            $numeroCuenta,
            $cci,
            $esParaDetraccion
        );

        $cuentaActualizada = CuentasBancariasData::get_cuenta_bancaria_by_id($idCuentaBancaria);
        return ApiResponse::success($cuentaActualizada, "Cuenta bancaria actualizada correctamente");
    }
}