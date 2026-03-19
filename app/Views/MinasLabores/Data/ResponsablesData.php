<?php

namespace App\Views\MinasLabores\Data;

use App\Models\ResponsableMina;
use App\Shared\Enums\EstadoBase;
use Illuminate\Support\Facades\DB;

class ResponsablesData
{
    /**
     * Historial de responsables de la mina
     */
    public static function get_historial_responsables(?int $id_mina = null, ?int $id_responsable_mina = null)
    {
        $sql = '
        SELECT DISTINCT
            res.id AS id_responsable_mina,
            CONCAT(em.nombre, " ", em.apellido) as empleado,
            em.dni,
            em.path_foto,
            res.fecha_inicio,
            res.fecha_fin,
            res.estado
        FROM
            empleado em
        INNER JOIN responsable_mina res ON
            res.id_empleado = em.id
        WHERE
            1 = 1
        ';

        $params = [];

        if ($id_responsable_mina !== null) {
            $sql .= ' AND res.id = :id_responsable_mina';
            $params['id_responsable_mina'] = $id_responsable_mina;

            return DB::selectOne($sql, $params);
        }

        if ($id_mina !== null) {
            $sql .= ' AND res.id_mina = :id_mina';
            $params['id_mina'] = $id_mina;
        }

        $sql .= ' ORDER BY res.fecha_inicio DESC';

        return DB::select($sql, $params);
    }

    public static function get_responsable_by_id(int $id_responsable_mina)
    {
        return self::get_historial_responsables(id_responsable_mina: $id_responsable_mina);
    }

    public static function nuevo_responsable(int $id_mina, int $id_empleado, string $fecha_inicio)
    {
        return ResponsableMina::insertGetId([
            'id_mina' => $id_mina,
            'id_empleado' => $id_empleado,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => null,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    public static function update_fecha_fin_responsabilidad(int $id_mina, string $fecha_fin)
    {
        ResponsableMina::where('id_mina', $id_mina)
            ->where('estado', EstadoBase::Activo->value)
            ->update([
                'fecha_fin' => $fecha_fin,
                'estado' => EstadoBase::Inactivo->value,
            ]);
    }

    /**
     * Posibles empleados a elegir para responsable de una mina específica
     */
    public static function get_empleados_disponibles(int $id_mina)
    {
        $sql = '
        SELECT DISTINCT
            em.id AS id_empleado,
            CONCAT(em.nombre, " ", em.apellido) AS empleado
        FROM
            empleado em
        WHERE
            em.estado = "Activo" AND
            -- que no sean el responsable actual de esta mina
            em.id NOT IN (
                SELECT
                    res.id_empleado
                FROM
                    responsable_mina res
                WHERE
                    res.id_mina = :id_mina AND
                    res.estado = "Activo" AND
                    res.fecha_fin IS NULL
            )
        ';

        return DB::select($sql, [
            'id_mina' => $id_mina,
        ]);
    }
}
