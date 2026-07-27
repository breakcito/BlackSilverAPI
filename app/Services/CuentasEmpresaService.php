<?php

namespace App\Services;

use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\Moneda;
use App\Shared\Responses\ApiResponse;
use App\Data\CuentasEmpresaData;

class CuentasEmpresaService
{
    public static function crear_cuenta(
        int $id_empresa,
        int $id_banco,
        Moneda $moneda,
        string $numero_cuenta,
        ?string $cci,
        bool $es_para_detraccion
    ): array {
        $ya_existe = CuentasEmpresaData::ya_existe($id_empresa, $id_banco, $numero_cuenta, $cci);

        if ($ya_existe) {
            return ApiResponse::error("Esta cuenta bancaria ya está registrada.");
        }

        $id = CuentasEmpresaData::crear_cuenta(
            id_empresa: $id_empresa,
            id_banco: $id_banco,
            moneda: $moneda,
            numero_cuenta: $numero_cuenta,
            cci: $cci,
            es_para_detraccion: $es_para_detraccion
        );

        $nuevaCuenta = CuentasEmpresaData::get_cuentas(id_cuenta_bancaria: $id);
        return ApiResponse::success($nuevaCuenta, "Cuenta bancaria registrada correctamente");
    }

    public static function get_cuentas(
        int|array|null $id_empresa = null,
        int|array|null $id_cuenta_bancaria = null,
        ?EstadoBase $estado = EstadoBase::Activo,
    ) {
        $data = CuentasEmpresaData::get_cuentas(
            id_cuenta_bancaria: $id_cuenta_bancaria,
            id_empresa: $id_empresa,
            estado: $estado
        );

        return ApiResponse::success($data);
    }
}