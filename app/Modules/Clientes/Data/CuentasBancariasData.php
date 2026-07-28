<?php

namespace App\Modules\Clientes\Data;

use App\Models\CuentaBancariaCliente;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class CuentasBancariasData
{
    public static function get_cuentas_bancarias(
        ?int $id_cliente = null,
        ?int $id_cuenta_bancaria = null,
        ?array $ids_cliente = null,
        ?bool $solo_activas = null
    ) {
        $sql = '
        SELECT
            cn.id_cliente AS id_cliente,
            cn.id AS id_cuenta_bancaria,
            cn.id_banco,
            bc.nombre as banco,
            bc.abreviatura as banco_abv,
            bc.es_nacional,
            cn.moneda,
            cn.numero_cuenta,
            cn.cci,
            cn.es_para_detraccion,
            cn.estado
        FROM
            cuenta_bancaria_cliente cn
        INNER JOIN banco bc ON bc.id = cn.id_banco
        WHERE 1 = 1
        ';

        $params = [];
        if ($id_cliente !== null) {
            $sql .= ' AND cn.id_cliente = :id_cliente';
            $params['id_cliente'] = $id_cliente;
        } elseif ($ids_cliente !== null && count($ids_cliente) > 0) {
            $placeholders = [];
            foreach ($ids_cliente as $i => $id) {
                $key = "id_cliente_$i";
                $placeholders[] = ":$key";
                $params[$key] = $id;
            }
            $sql .= ' AND cn.id_cliente IN (' . implode(',', $placeholders) . ')';
        }

        if ($id_cuenta_bancaria !== null) {
            $sql .= ' AND cn.id = :id_cuenta_bancaria';
            $params['id_cuenta_bancaria'] = $id_cuenta_bancaria;
            return (array) DB::selectOne($sql, $params);
        }

        if ($solo_activas === true) {
            $sql .= ' AND cn.estado = :estado_activo';
            $params['estado_activo'] = EstadoBase::Activo->value;
        }

        $sql .= ' ORDER BY cn.es_para_detraccion DESC, cn.moneda, cn.numero_cuenta;';
        return DB::select($sql, $params);
    }

    public static function get_cuenta_bancaria_by_id(int $id_cuenta_bancaria): array
    {
        return self::get_cuentas_bancarias(id_cuenta_bancaria: $id_cuenta_bancaria);
    }

    public static function crear_cuenta_bancaria(
        int $id_cliente,
        int $id_banco,
        string $moneda,
        string $numeroCuenta,
        ?string $cci,
        int $esParaDetraccion
    ): int {
        return CuentaBancariaCliente::insertGetId([
            'id_cliente' => $id_cliente,
            'id_banco' => $id_banco,
            'moneda' => $moneda,
            'numero_cuenta' => $numeroCuenta,
            'cci' => $cci,
            'es_para_detraccion' => $esParaDetraccion,
            'estado' => EstadoBase::Activo->value
        ]);
    }

    public static function actualizar_cuenta_bancaria(
        int $id_cuenta_bancaria,
        int $id_banco,
        string $moneda,
        string $numeroCuenta,
        ?string $cci,
        int $esParaDetraccion
    ): bool {
        return (bool) CuentaBancariaCliente::where('id', $id_cuenta_bancaria)
            ->update([
                'id_banco' => $id_banco,
                'moneda' => $moneda,
                'numero_cuenta' => $numeroCuenta,
                'cci' => $cci,
                'es_para_detraccion' => $esParaDetraccion,
            ]);
    }

    public static function existe_cuenta_bancaria(
        int $id_cliente,
        int $id_banco,
        string $numero_cuenta,
        ?int $excluir_id = null
    ): bool {
        return CuentaBancariaCliente::where('id_cliente', $id_cliente)
            ->where('id_banco', $id_banco)
            ->where('numero_cuenta', $numero_cuenta)
            ->when($excluir_id !== null, fn($q) => $q->where('id', '!=', $excluir_id))
            ->exists();
    }

    public static function get_cliente_id_by_cuenta(int $id_cuenta_bancaria): ?int
    {
        $row = DB::table('cuenta_bancaria_cliente')
            ->where('id', $id_cuenta_bancaria)
            ->first(['id_cliente']);
        return $row ? (int) $row->id_cliente : null;
    }
}