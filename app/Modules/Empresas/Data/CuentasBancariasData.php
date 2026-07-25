<?php

namespace App\Modules\Empresas\Data;

use App\Models\CuentaBancariaEmpresa;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class CuentasBancariasData
{
    public static function get_cuentas(
        ?int $id_empresa = null,
        ?int $id_cuenta_bancaria = null
    ): array {
        $sql = '
        SELECT
            cn.id AS id_cuenta_bancaria,
            bc.nombre as banco,
            bc.abreviatura as banco_abv,
            cn.moneda,
            cn.numero_cuenta,
            cn.cci,
            cn.es_para_detraccion,
            cn.estado
        FROM
            cuenta_bancaria_empresa cn
        INNER JOIN banco bc ON bc.id = cn.id_banco
        WHERE 1 = 1
        ';

        $params = [];
        if ($id_empresa !== null) {
            $sql .= ' AND cn.id_empresa = :id_empresa';
            $params['id_empresa'] = $id_empresa;
        }

        if ($id_cuenta_bancaria !== null) {
            $sql .= ' AND cn.id = :id_cuenta_bancaria';
            $params['id_cuenta_bancaria'] = $id_cuenta_bancaria;
            return (array) DB::selectOne($sql, $params);
        }

        $sql .= ' ORDER BY cn.es_para_detraccion DESC, cn.moneda, cn.numero_cuenta;';
        return DB::select($sql, $params);
    }

    public static function crear_cuenta(
        int $id_empresa,
        int $id_banco,
        string $moneda,
        string $numero_cuenta,
        ?string $cci,
        bool $es_para_detraccion
    ): int {
        return CuentaBancariaEmpresa::insertGetId([
            'id_empresa' => $id_empresa,
            'id_banco' => $id_banco,
            'moneda' => $moneda,
            'numero_cuenta' => $numero_cuenta,
            'cci' => $cci,
            'es_para_detraccion' => $es_para_detraccion ? 1 : 0,
            'estado' => EstadoBase::Activo->value
        ]);
    }

    public static function ya_existe(int $id_empresa, int $id_banco, ?string $numero_cuenta, ?string $cci): bool
    {
        // Validamos que al menos uno de los dos datos contenga valor
        if (blank($numero_cuenta) && blank($cci)) {
            return false;
        }

        return CuentaBancariaEmpresa::where('id_empresa', $id_empresa)
            ->where('id_banco', $id_banco)
            ->where(function ($query) use ($numero_cuenta, $cci) {
                if (filled($numero_cuenta)) {
                    $query->orWhere('numero_cuenta', $numero_cuenta);
                }
                if (filled($cci)) {
                    $query->orWhere('cci', $cci);
                }
            })
            ->exists();
    }
}
