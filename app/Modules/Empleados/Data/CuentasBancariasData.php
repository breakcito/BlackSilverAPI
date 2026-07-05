<?php

namespace App\Modules\Empleados\Data;

use App\Models\CuentaBancariaEmpleado;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class CuentasBancariasData
{
    /**
     * Obtener listado de cuentas bancarias de empleados.
     */
    public static function get_cuentas_bancarias(
        ?int $id_empleado = null,
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
            cn.estado
        FROM
            cuenta_bancaria_empleado cn
        INNER JOIN banco bc ON bc.id = cn.id_banco
        WHERE 1 = 1
        ';

        $params = [];
        if ($id_empleado !== null) {
            $sql .= ' AND cn.id_empleado = :id_empleado';
            $params['id_empleado'] = $id_empleado;
        }

        if ($id_cuenta_bancaria !== null) {
            $sql .= ' AND cn.id = :id_cuenta_bancaria';
            $params['id_cuenta_bancaria'] = $id_cuenta_bancaria;
            return (array) DB::selectOne($sql, $params);
        }

        $sql .= ' ORDER BY cn.moneda, cn.numero_cuenta;';
        return DB::select($sql, $params);
    }

    /**
     * Obtener una cuenta bancaria por su ID.
     */
    public static function get_cuenta_bancaria_by_id(int $id_cuenta_bancaria): array
    {
        return self::get_cuentas_bancarias(id_cuenta_bancaria: $id_cuenta_bancaria);
    }

    /**
     * Registrar una cuenta bancaria en base de datos.
     */
    public static function crear_cuenta_bancaria(
        int $id_empleado,
        int $id_banco,
        string $moneda,
        string $numeroCuenta,
        ?string $cci
    ): int {
        return CuentaBancariaEmpleado::insertGetId([
            'id_empleado' => $id_empleado,
            'id_banco' => $id_banco,
            'moneda' => $moneda,
            'numero_cuenta' => $numeroCuenta,
            'cci' => $cci,
            'estado' => EstadoBase::Activo->value
        ]);
    }

    /**
     * Verificar si una cuenta bancaria ya existe para el empleado.
     */
    public static function existe_cuenta_bancaria(int $id_empleado, int $id_banco, string $numero_cuenta): bool
    {
        return CuentaBancariaEmpleado::where('id_empleado', $id_empleado)
            ->where('id_banco', $id_banco)
            ->where('numero_cuenta', $numero_cuenta)
            ->exists();
    }
}
