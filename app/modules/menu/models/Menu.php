<?php

namespace App\Modules\Sistema\Infraestructure\Models;

use App\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Modelo que gestionara los modulos, submodulos y secciones del sistema
 */
class Menu extends Model
{
    public static function get_secciones_by_rol(int $id_rol): array
    {
        $sql = '
            SELECT
                m.id,
                m.nombre,
                m.descripcion,
                m.estado,
                COUNT(s.id) as total_submodulos
            FROM modulo m
            LEFT JOIN submodulo s ON s.id_modulo = m.id AND s.estado = ?
            WHERE m.estado = ?
            GROUP BY m.id, m.nombre, m.descripcion, m.estado
            ORDER BY m.nombre
        ';

        return DB::select($sql, [
            EstadoBase::Activo->value,
            EstadoBase::Activo->value,
        ]);
    }
}
