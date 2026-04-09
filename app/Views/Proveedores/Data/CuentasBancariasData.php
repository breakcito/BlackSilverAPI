<?php

namespace App\Views\Proveedores\Data;

use Illuminate\Support\Facades\DB;

class CuentasBancariasData
{
    public function get_cuentas_bancarias(int $id_proveedor): array
    {
        return DB::select('
            SELECT
                cn.id AS id_cuenta_bancaria,
                bc.abreviatura as banco_abv,
                bc.nombre as banco_nombre,
                cn.id_banco,
                cn.moneda,
                cn.numero_cuenta,
                cn.cci,
                cn.es_para_detraccion,
                cn.estado
            FROM
                cuenta_bancaria_proveedor cn
            INNER JOIN banco bc ON bc.id = cn.id_banco
            WHERE cn.id_proveedor = ?
            ORDER BY cn.id DESC;
        ', [$id_proveedor]);
    }

    public function crear_cuenta_bancaria(int $id_proveedor, int $id_banco, string $moneda, string $numeroCuenta, ?string $cci, int $esParaDetraccion): int
    {
        return DB::table('cuenta_bancaria_proveedor')->insertGetId([
            'id_proveedor' => $id_proveedor,
            'id_banco' => $id_banco,
            'moneda' => $moneda,
            'numero_cuenta' => $numeroCuenta,
            'cci' => $cci,
            'es_para_detraccion' => $esParaDetraccion,
            'estado' => 'Activa'
        ]);
    }
}
