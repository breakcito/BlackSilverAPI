<?php

namespace App\Data;

use App\Models\Cargo;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class CargosData
{
    /**
     * Metodo generico para obtener los cargos
     */
    public static function get_cargos(
        ?int $id_cargo = null,
        $id_area = null,
        ?EstadoBase $estado = null,
        ?bool $con_area = null,
    ) {
        $sql = '
        SELECT
            c.id AS id_cargo,
            c.id_area,
            c.nombre,
            c.estado
        FROM cargo c
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_cargo !== null) {
            $sql .= " AND c.id = :id_cargo";
            $params['id_cargo'] = $id_cargo;
            return DB::selectOne($sql, $params);
        }

        if ($con_area === false) {
            $sql .= " AND c.id_area IS NULL";
        } elseif ($con_area === true) {
            $sql .= " AND c.id_area IS NOT NULL";
        } elseif ($id_area !== null) {
            if (is_array($id_area)) {
                if (!empty($id_area)) {
                    $sql .= " AND c.id_area IN (" . implode(',', array_map('intval', $id_area)) . ")";
                } else {
                    $sql .= " AND 1 = 0";
                }
            } else {
                $sql .= " AND c.id_area = :id_area";
                $params['id_area'] = $id_area;
            }
        }

        if ($estado !== null) {
            $sql .= " AND c.estado = :estado";
            $params['estado'] = $estado->value;
        }

        $sql .= " ORDER BY c.nombre;";
        return DB::select($sql, $params);
    }

    /**
     * Crear cargo
     */
    public static function crear_cargo(string $nombre, ?int $id_area = null): int
    {
        return Cargo::insertGetId([
            'nombre' => $nombre,
            'id_area' => $id_area,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Verificar duplicado en la misma área
     */
    public static function verificar_nombre_duplicado(string $nombre, ?int $id_area = null): bool
    {
        $query = Cargo::where('nombre', $nombre);

        if ($id_area === null) {
            $query->whereNull('id_area');
        } else {
            $query->where('id_area', $id_area);
        }

        return $query->exists();
    }

    /**
     * Actualizar el área a la que pertenece un cargo (puede ser null para quitarlo de un área).
     */
    public static function actualizar_area(int $id_cargo, ?int $id_area): void
    {
        Cargo::findOrFail($id_cargo)->update(['id_area' => $id_area]);
    }

    /**
     * Alternar estado
     */
    public static function cambiar_estado(int $id_cargo): string
    {
        $cargo = Cargo::findOrFail($id_cargo);
        $nuevo_estado = $cargo->estado === EstadoBase::Activo->value
            ? EstadoBase::Inactivo->value
            : EstadoBase::Activo->value;

        $cargo->update(['estado' => $nuevo_estado]);

        return $nuevo_estado;
    }
}
