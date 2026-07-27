<?php

namespace App\Modules\Empresas\Service;

use App\Data\CuentasEmpresaData as CuentasEmpresaDataGlobal;
use App\Modules\Empresas\Data\CuentasData;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\Moneda;
use App\Shared\Responses\ApiResponse;

class CuentasService
{

    public static function actualizar_cuenta(
        int $id_cuenta_bancaria,
        int $id_banco,
        Moneda $moneda,
        string $numero_cuenta,
        ?string $cci,
        bool $es_para_detraccion
    ) {
        $cuenta = CuentasEmpresaDataGlobal::get_by_id($id_cuenta_bancaria, ['id_empresa']);
        $id_empresa = (int) $cuenta['id_empresa'];

        $ya_existe = CuentasEmpresaDataGlobal::ya_existe($id_empresa, $id_banco, $numero_cuenta, $cci, excluir_id: $id_cuenta_bancaria);

        if ($ya_existe) {
            return ApiResponse::error("Esta cuenta bancaria ya está registrada.");
        }

        CuentasData::actualizar_cuenta(
            id_cuenta_bancaria: $id_cuenta_bancaria,
            id_banco: $id_banco,
            moneda: $moneda,
            numero_cuenta: $numero_cuenta,
            cci: $cci,
            es_para_detraccion: $es_para_detraccion
        );

        $cuentaActualizada = CuentasEmpresaDataGlobal::get_cuentas(id_cuenta_bancaria: $id_cuenta_bancaria);
        return ApiResponse::success($cuentaActualizada, "Cuenta bancaria actualizada correctamente");
    }

    public static function cambiar_estado_cuenta(
        int $id_cuenta_bancaria,
        EstadoBase $estado
    ) {

        CuentasData::cambiar_estado($id_cuenta_bancaria, $estado);
        return ApiResponse::success(null, "Estado de la cuenta bancaria actualizado correctamente");
    }
}
