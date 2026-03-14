<?php

namespace App\Views\Cuentas\Data;

use App\Models\Usuario;
use App\Models\Empleado;
use App\Models\Rol;
use App\Models\Empresa;
use App\Models\UsuarioEmpresa;
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
                ep.nombre_comercial as empresa_pertenece,
                (
                    SELECT GROUP_CONCAT(emp.abreviatura SEPARATOR ', ')
                    FROM usuario_empresa ue
                    INNER JOIN empresa emp ON emp.id = ue.id_empresa
                    WHERE ue.id_usuario = u.id
                ) as empresas_acceso
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

    /**
     * Obtener las empresas a las que un usuario tiene acceso
     */
    public static function get_empresas_usuario(int $id_usuario): array
    {
        $sql = "
            SELECT 
                e.id as id_empresa,
                e.razon_social,
                e.nombre_comercial,
                e.abreviatura,
                e.path_logo
            FROM usuario_empresa ue
            INNER JOIN empresa e ON e.id = ue.id_empresa
            WHERE ue.id_usuario = :id_usuario
        ";

        return DB::select($sql, ['id_usuario' => $id_usuario]);
    }

    /**
     * Obtener todas las empresas activas para selección
     */
    public static function get_todas_las_empresas(): array
    {
        return Empresa::select('id', 'razon_social', 'nombre_comercial', 'abreviatura')
            ->orderBy('razon_social', 'ASC')
            ->get()
            ->toArray();
    }

    /**
     * Verificar si un usuario ya tiene acceso a una empresa específica
     */
    public static function existe_vinculo_empresa(int $id_usuario, int $id_empresa): bool
    {
        return UsuarioEmpresa::where('id_usuario', $id_usuario)
            ->where('id_empresa', $id_empresa)
            ->exists();
    }
    
    /**
     * Contar cuantas empresas tiene asignadas un usuario
     */
    public static function contar_empresas_usuario(int $id_usuario): int
    {
        return UsuarioEmpresa::where('id_usuario', $id_usuario)->count();
    }
}
