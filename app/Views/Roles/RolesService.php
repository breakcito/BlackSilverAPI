<?php

namespace App\Views\Roles;

use App\Shared\Responses\ApiResponse;
use App\Views\Roles\Data\RolesData;
use App\Views\Roles\Data\PermisosData;
use Illuminate\Support\Facades\DB;

class RolesService
{
    /**
     * Obtener listado de roles
     */
    public static function get_roles()
    {
        $roles = RolesData::get_roles();
        return ApiResponse::success($roles);
    }

    /**
     * Obtener la estructura completa para el selector de permisos
     */
    public static function get_estructura_permisos()
    {
        $estructura = PermisosData::get_estructura_permisos();
        return ApiResponse::success($estructura);
    }

    /**
     * Crear un rol y asignar sus secciones
     */
    public static function crear_rol(array $data)
    {
        try {
            return DB::transaction(function () use ($data) {
                
                // 1. Crear el objeto rol
                $id_rol = RolesData::crear_rol([
                    'nombre' => $data['nombre'],
                    'descripcion' => $data['descripcion'] ?? null,
                    'estado' => 'Activo'
                ]);

                // 2. Asignar secciones
                foreach ($data['secciones'] as $id_seccion) {
                    PermisosData::asignar_seccion_a_rol($id_rol, $id_seccion);
                }

                $nuevoRol = RolesData::get_rol_by_id($id_rol);
                return ApiResponse::success($nuevoRol, 'Rol creado correctamente con sus permisos.');
            });
        } catch (\Exception $e) {
            return ApiResponse::error('Ocurrió un error al registrar el rol: ' . $e->getMessage());
        }
    }
}
