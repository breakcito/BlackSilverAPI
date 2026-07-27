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
        if ($es_para_detraccion && !CuentasEmpresaData::banco_es_nacional($id_banco)) {
            return ApiResponse::error("Solo las cuentas del Banco de la Nación pueden marcarse para detracción.");
        }

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

    public static function actualizar_cuenta(
        int $id_cuenta_bancaria,
        int $id_banco,
        Moneda $moneda,
        string $numero_cuenta,
        ?string $cci,
        bool $es_para_detraccion
    ): array {
        $cuenta = CuentasEmpresaData::get_by_id($id_cuenta_bancaria);
        if (!$cuenta) {
            return ApiResponse::error("La cuenta bancaria no existe.");
        }

        if ($es_para_detraccion && !CuentasEmpresaData::banco_es_nacional($id_banco)) {
            return ApiResponse::error("Solo las cuentas del Banco de la Nación pueden marcarse para detracción.");
        }

        $id_empresa = (int) $cuenta->id_empresa;
        $ya_existe = CuentasEmpresaData::ya_existe($id_empresa, $id_banco, $numero_cuenta, $cci, excluir_id: $id_cuenta_bancaria);

        if ($ya_existe) {
            return ApiResponse::error("Esta cuenta bancaria ya está registrada.");
        }

        CuentasEmpresaData::actualizar_cuenta(
            id_cuenta_bancaria: $id_cuenta_bancaria,
            id_banco: $id_banco,
            moneda: $moneda,
            numero_cuenta: $numero_cuenta,
            cci: $cci,
            es_para_detraccion: $es_para_detraccion
        );

        $cuentaActualizada = CuentasEmpresaData::get_cuentas(id_cuenta_bancaria: $id_cuenta_bancaria);
        return ApiResponse::success($cuentaActualizada, "Cuenta bancaria actualizada correctamente");
    }

    public static function cambiar_estado(
        int $id_cuenta_bancaria,
        EstadoBase $estado
    ): array {
        $cuenta = CuentasEmpresaData::get_by_id($id_cuenta_bancaria);
        if (!$cuenta) {
            return ApiResponse::error("La cuenta bancaria no existe.");
        }

        CuentasEmpresaData::cambiar_estado($id_cuenta_bancaria, $estado);

        return ApiResponse::success(null, "Estado de la cuenta bancaria actualizado correctamente");
    }
}