<?php

namespace App\Modules\Empresas\Data;

use App\Models\CuentaBancariaEmpresa;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\Moneda;

class CuentasData
{
    public static function actualizar_cuenta(
        int $id_cuenta_bancaria,
        int $id_banco,
        Moneda $moneda,
        string $numero_cuenta,
        ?string $cci,
        bool $es_para_detraccion
    ): bool {
        return (bool) CuentaBancariaEmpresa::where('id', $id_cuenta_bancaria)
            ->update([
                'id_banco' => $id_banco,
                'moneda' => $moneda->value,
                'numero_cuenta' => $numero_cuenta,
                'cci' => $cci,
                'es_para_detraccion' => $es_para_detraccion ? 1 : 0,
            ]);
    }

    public static function cambiar_estado(
        int $id_cuenta_bancaria,
        EstadoBase $estado
    ): bool {
        return (bool) CuentaBancariaEmpresa::where('id', $id_cuenta_bancaria)
            ->update(['estado' => $estado->value]);
    }
}
