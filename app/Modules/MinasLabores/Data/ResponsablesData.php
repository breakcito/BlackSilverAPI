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
    public static function get_historial_responsables(?int $id_mina = null, ?int $id_responsable_mina = null)
    {
        $sql = '
        SELECT DISTINCT
            res.id AS id_responsable_mina,
            c.id as id_contratista,
            CONCAT(c.nombre, " ", c.apellido) as contratista,
            c.dni,
            c.path_foto,
            res.fecha_inicio,
            res.fecha_fin,
            res.estado
        FROM
            empleado c
        INNER JOIN responsable_mina res ON
            res.id_empleado_contratista = c.id
        WHERE
            c.es_contratista = 1
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

    public static function nuevo_responsable(int $id_mina, int $id_contratista, string $fecha_inicio)
    {
        return ResponsableMina::insertGetId([
            'id_mina' => $id_mina,
            'id_empleado_contratista' => $id_contratista,
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

    public static function inactivar_responsable(int $id_responsable_mina, string $fecha_fin)
    {
        ResponsableMina::where('id', $id_responsable_mina)
            ->update([
                'fecha_fin' => $fecha_fin,
                'estado' => EstadoBase::Inactivo->value,
            ]);
    }

    /**
     * Posibles contratistas a elegir para responsable de una mina específica
     */
    public static function get_contratistas_disponibles(int $id_mina)
    {
        $sql = '
        SELECT DISTINCT
            c.id AS id_contratista,
            CONCAT(c.nombre, " ", c.apellido) AS contratista
        FROM
            empleado c
        WHERE
            c.es_contratista = 1 AND
            c.estado = "Activo" AND
            -- que no sean el responsable actual de esta mina
            c.id NOT IN (
                SELECT
                    res.id_empleado_contratista
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
