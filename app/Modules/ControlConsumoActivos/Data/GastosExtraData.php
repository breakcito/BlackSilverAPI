<?php

namespace App\Modules\ControlConsumoActivos\Data;

use App\Models\GastoExtra;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class GastosExtraData
{
    /**
     * Obtener los gastos extra activos de un periodo (mes y año).
     */
    public static function get_gastos(int $mes, int $yearcito): array
    {
        $sql = '
        SELECT
            g.id as id_gasto_extra,
            g.id_labor,
            lb.nombre as labor,
            lb.id_mina,
            mn.nombre as mina,
            g.id_empleado_registro,
            CONCAT(emp.nombre, " ", emp.apellido) as empleado_registro,
            emp.id_cargo as id_cargo_registro,
            cargo_reg.nombre as cargo_registro,
            g.descripcion,
            g.monto,
            g.created_at,
            g.estado
        FROM gasto_extra g
        INNER JOIN labor lb ON lb.id = g.id_labor
        LEFT JOIN mina mn ON mn.id = lb.id_mina
        LEFT JOIN empleado emp ON emp.id = g.id_empleado_registro
        LEFT JOIN cargo cargo_reg ON cargo_reg.id = emp.id_cargo
        WHERE g.estado = :estado
          AND MONTH(g.created_at) = :mes
          AND YEAR(g.created_at) = :yearcito
        ORDER BY g.created_at DESC
        ';

        return DB::select($sql, [
            'estado' => EstadoBase::Activo->value,
            'mes' => $mes,
            'yearcito' => $yearcito,
        ]);
    }

    /**
     * Obtener un gasto extra por su ID.
     */
    public static function get_gasto_por_id(int $id_gasto): ?object
    {
        $sql = '
        SELECT
            g.id as id_gasto_extra,
            g.id_labor,
            lb.nombre as labor,
            lb.id_mina,
            mn.nombre as mina,
            g.id_empleado_registro,
            CONCAT(emp.nombre, " ", emp.apellido) as empleado_registro,
            emp.id_cargo as id_cargo_registro,
            cargo_reg.nombre as cargo_registro,
            g.descripcion,
            g.monto,
            g.created_at,
            g.estado
        FROM gasto_extra g
        INNER JOIN labor lb ON lb.id = g.id_labor
        LEFT JOIN mina mn ON mn.id = lb.id_mina
        LEFT JOIN empleado emp ON emp.id = g.id_empleado_registro
        LEFT JOIN cargo cargo_reg ON cargo_reg.id = emp.id_cargo
        WHERE g.id = :id
        ';

        return DB::selectOne($sql, ['id' => $id_gasto]);
    }

    /**
     * Registrar un nuevo gasto extra.
     */
    public static function crear_gasto(
        int $id_empleado_registro,
        int $id_labor,
        string $descripcion,
        float $monto
    ): int {
        return GastoExtra::crear_gasto(
            id_empleado_registro: $id_empleado_registro,
            id_labor: $id_labor,
            descripcion: $descripcion,
            monto: $monto,
            estado: EstadoBase::Activo
        );
    }
}
