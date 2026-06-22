<?php

namespace App\Modules\MinasLabores\Data;

use App\Models\ResponsableMina;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class ResponsablesData
{
    /**
     * Historial de responsables de la mina
     */
    public static function get_responsables(
        ?int $id_mina = null,
        ?int $id_responsable = null,
        ?EstadoBase $estado = EstadoBase::Activo
    ) {
        $sql = '
        SELECT
            res.id AS id_responsable,
            
            emp.id as id_empleado,
            CONCAT(emp.nombre, " ", emp.apellido) as nombre_completo,
            emp.dni,
            emp.ruc,
            emp.url_foto,
            
            res.fecha_inicio,
            res.fecha_fin,
            res.estado
        FROM responsable_mina res
        INNER JOIN empleado emp ON
            res.id_empleado = emp.id
        WHERE 1=1
        ';

        $params = [];

        if ($id_responsable !== null) {
            $sql .= ' AND res.id = :id_responsable';
            $params['id_responsable'] = $id_responsable;

            return DB::selectOne($sql, $params);
        }

        if ($estado !== null) {
            $sql .= ' AND res.estado = :estado';
            $params['estado'] = $estado->value;
        }

        if ($id_mina !== null) {
            $sql .= ' AND res.id_mina = :id_mina';
            $params['id_mina'] = $id_mina;
        }

        $sql .= ' ORDER BY res.fecha_inicio DESC';

        return DB::select($sql, $params);
    }

    /**
     * Registrar un nuevo responsable de mina
     */
    public static function crear_responsable(
        int $id_mina,
        int $id_empleado,
        ?string $fecha_inicio
    ) {
        return ResponsableMina::insertGetId([
            'id_mina' => $id_mina,
            'id_empleado' => $id_empleado,
            'fecha_inicio' => $fecha_inicio ?? now(),
            'fecha_fin' => null,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    public static function update_fecha_fin_responsabilidad(
        int $id_mina,
        ?string $fecha_fin = null,
    ) {
        ResponsableMina::where('id_mina', $id_mina)
            ->where('estado', EstadoBase::Activo->value)
            ->update([
                'fecha_fin' => $fecha_fin ?? now(),
                'estado' => EstadoBase::Inactivo->value,
            ]);
    }

    public static function inactivar_responsable(
        int $id_responsable_mina,
        ?string $fecha_fin = null
    ) {
        ResponsableMina::where('id', $id_responsable_mina)
            ->update([
                'fecha_fin' => $fecha_fin ?? now(),
                'estado' => EstadoBase::Inactivo->value,
            ]);
    }
}
