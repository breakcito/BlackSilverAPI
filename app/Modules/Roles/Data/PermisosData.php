<?php

namespace App\Modules\Roles\Data;

use App\Models\Menu;
use App\Models\Submenu;
use App\Models\Modulo;
use App\Models\ModuloRol;

class PermisosData
{
    /**
     * Obtener la estructura jerarquica de modulos -> submodulos -> secciones
     */
    public static function get_estructura_permisos()
    {
        // 1. Obtener modulos activos
        $modulos = Menu::where('estado', 'Activo')->get();

        foreach ($modulos as $modulo) {
            // 2. Obtener submodulos de cada modulo
            $submodulos = Submenu::where('id_modulo', $modulo->id)
                ->where('estado', 'Activo')
                ->get();

            foreach ($submodulos as $submodulo) {
                // 3. Obtener secciones de cada submodulo
                $submodulo->secciones = Modulo::where('id_submodulo', $submodulo->id)
                    ->where('estado', 'Activo')
                    ->get();
            }

            $modulo->submodulos = $submodulos;
        }

        return $modulos;
    }

    /**
     * Asignar una seccion a un rol (tabla pivote)
     */
    public static function asignar_seccion_a_rol(int $id_rol, int $id_seccion): void
    {
        ModuloRol::create([
            'id_rol' => $id_rol,
            'id_seccion' => $id_seccion
        ]);
    }

    /**
     * Obtener solo los IDs de las secciones de un rol
     */
    public static function get_ids_secciones_por_rol(int $id_rol): array
    {
        return ModuloRol::where('id_rol', $id_rol)
            ->pluck('id_seccion')
            ->toArray();
    }

    /**
     * Eliminar todas las asociaciones de secciones de un rol
     */
    public static function limpiar_permisos_rol(int $id_rol): void
    {
        ModuloRol::where('id_rol', $id_rol)->delete();
    }
}
