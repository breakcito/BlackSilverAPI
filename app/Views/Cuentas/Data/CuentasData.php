<?php

namespace App\Views\Cuentas\Data;

use App\Models\Rol;
use Illuminate\Support\Facades\DB;

class CuentasData
{
    /**
     * Listar todas las cuentas de usuario con información de empleado y rol
     */
    public static function get_cuentas(): array
    {
        $sql = "
            SELECT 
                u.id as id_usuario,
                u.username,
                u.estado,
                u.id_rol,
                u.id_empleado,
                r.nombre as nombre_rol,
                e.nombre as nombre_empleado,
                e.apellido as apellido_empleado,
                e.id_empresa as id_empresa_pertenece,
                e.path_foto,
                ep.nombre_comercial as empresa_pertenece
            FROM usuario u
            INNER JOIN empleado e ON e.id = u.id_empleado
            INNER JOIN empresa ep ON ep.id = e.id_empresa
            INNER JOIN rol r ON r.id = u.id_rol
            ORDER BY e.apellido ASC
        ";

        return DB::select($sql);
    }

    /**
     * Listar empleados que aún no tienen una cuenta de usuario creada
     */
    public static function get_empleados_sin_cuenta(): array
    {
        $sql = "
            SELECT 
                e.id,
                e.nombre,
                e.apellido,
                e.dni,
                e.id_empresa as id_empresa_pertenece
            FROM empleado e
            LEFT JOIN usuario u ON u.id_empleado = e.id
            WHERE u.id IS NULL AND e.estado = 'Activo'
            ORDER BY e.apellido ASC
        ";

        return DB::select($sql);
    }

    /**
     * Obtener los roles activos del sistema
     */
    public static function get_roles_disponibles(): array
    {
        return Rol::where('estado', 'Activo')
            ->select('id', 'nombre')
            ->orderBy('nombre', 'ASC')
            ->get()
            ->toArray();
    }
}
