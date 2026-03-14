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

    /**
     * Obtener los IDs de las secciones asignadas a un rol
     */
    public static function get_permisos_rol(int $id_rol)
    {
        $secciones = PermisosData::get_ids_secciones_por_rol($id_rol);
        return ApiResponse::success($secciones);
    }

    /**
     * Actualizar los permisos de un rol
     */
    public static function actualizar_permisos_rol(int $id_rol, array $secciones)
    {
        try {
            return DB::transaction(function () use ($id_rol, $secciones) {
                // 1. Limpiar permisos actuales
                PermisosData::limpiar_permisos_rol($id_rol);

                // 2. Asignar nuevos permisos
                foreach ($secciones as $id_seccion) {
                    PermisosData::asignar_seccion_a_rol($id_rol, $id_seccion);
                }

                return ApiResponse::success(null, 'Permisos actualizados correctamente.');
            });
        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar los permisos: ' . $e->getMessage());
        }
    }
}
