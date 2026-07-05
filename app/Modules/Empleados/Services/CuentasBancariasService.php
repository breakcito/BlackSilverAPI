<?php

namespace App\Modules\Empleados\Services;

use App\Modules\Empleados\Data\CuentasBancariasData;
use App\Shared\Responses\ApiResponse;

class CuentasBancariasService
{
    /**
     * Obtener cuentas bancarias del empleado.
     */
    public static function get_cuentas_bancarias(int $idEmpleado): array
    {
        $data = CuentasBancariasData::get_cuentas_bancarias($idEmpleado);
        return ApiResponse::success($data, "Cuentas bancarias obtenidas correctamente");
    }

    /**
     * Registrar una cuenta bancaria para el empleado.
     */
    public static function crear_cuenta_bancaria(
        int $idEmpleado,
        int $idBanco,
        string $moneda,
        string $numeroCuenta,
        ?string $cci
    ): array {
        $existe = CuentasBancariasData::existe_cuenta_bancaria($idEmpleado, $idBanco, $numeroCuenta);

        if ($existe) {
            return ApiResponse::error("Esta cuenta bancaria ya está registrada para este empleado");
        }

        $id = CuentasBancariasData::crear_cuenta_bancaria(
            $idEmpleado,
            $idBanco,
            $moneda,
            $numeroCuenta,
            $cci
        );
        $nuevaCuenta = CuentasBancariasData::get_cuenta_bancaria_by_id($id);
        return ApiResponse::success($nuevaCuenta, "Cuenta bancaria registrada correctamente");
    }
}
