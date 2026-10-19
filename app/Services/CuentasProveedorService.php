<?php

namespace App\Services;

use App\Data\CuentasProveedorData;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\Moneda;
use App\Shared\Responses\ApiResponse;

class CuentasProveedorService
{
    public static function get_cuentas(
        int|array|null $id_proveedor = null,
        int|array|null $id_cuenta_bancaria = null,
        ?Moneda $moneda = null,
        ?bool $es_para_detraccion = null,
        ?EstadoBase $estado = EstadoBase::Activo,
    ) {
        $data = CuentasProveedorData::get_cuentas(
            id_proveedor: $id_proveedor,
            id_cuenta_bancaria: $id_cuenta_bancaria,
            moneda: $moneda,
            es_para_detraccion: $es_para_detraccion,
            estado: $estado
        );

        return ApiResponse::success($data);
    }
}