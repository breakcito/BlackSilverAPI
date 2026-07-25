<?php

namespace App\Modules\Empresas\Services;

use App\Shared\Responses\ApiResponse;
use App\Modules\Empresas\Data\CuentasBancariasData;

class CuentasBancariasService
{
    public static function crear_cuenta(
        int $id_empresa,
        int $id_banco,
        string $moneda,
        string $numero_cuenta,
        ?string $cci,
        bool $es_para_detraccion
    ): array {
        $ya_existe = CuentasBancariasData::ya_existe($id_empresa, $id_banco, $numero_cuenta, $cci);

        if ($ya_existe) {
            return ApiResponse::error("Esta cuenta bancaria ya está registrada.");
        }

        $id = CuentasBancariasData::crear_cuenta(
            id_empresa: $id_empresa,
            id_banco: $id_banco,
            moneda: $moneda,
            numero_cuenta: $numero_cuenta,
            cci: $cci,
            es_para_detraccion: $es_para_detraccion
        );

        $nuevaCuenta = CuentasBancariasData::get_cuentas(id_cuenta_bancaria: $id);
        return ApiResponse::success($nuevaCuenta, "Cuenta bancaria registrada correctamente");
    }
}
