<?php

namespace App\Modules\Menu\Services;

use App\Modules\Menu\Models\Menu;
use App\Shared\Responses\ApiResponse;

/**
 * Servicio para lógica de negocio del menú de navegación.
 */
class MenuService
{
    /**
     * Obtener el menú de navegación completo para un rol.
     */
    public function obtenerMenuPorRol(int $idRol): array
    {
        $modulos = Menu::get_modulos_by_rol($idRol);

        $menu = [];

        foreach ($modulos as $modulo) {
            $submodulos = Menu::get_submodulos_by_rol_and_modulo($idRol, $modulo->id_modulo);

            $submodulosData = [];

            foreach ($submodulos as $submodulo) {
                $secciones = Menu::get_secciones_by_rol_and_submodulo($idRol, $submodulo->id_submodulo);

                $submodulosData[] = [
                    'id_submodulo' => $submodulo->id_submodulo,
                    'nombre' => $submodulo->nombre,
                    'secciones' => array_map(function ($seccion) {
                        return [
                            'id_seccion' => $seccion->id_seccion,
                            'nombre' => $seccion->nombre,
                            'url' => $seccion->url,
                        ];
                    }, $secciones),
                ];
            }

            $menu[] = [
                'id_modulo' => $modulo->id_modulo,
                'nombre' => $modulo->nombre,
                'submodulos' => $submodulosData,
            ];
        }

        return ApiResponse::array(true, $menu);
    }
}
