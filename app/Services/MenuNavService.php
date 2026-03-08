<?php

namespace App\Services;

use App\Shared\Responses\ApiResponse;
use App\Data\MenuNavData;


class MenuNavService
{

    public static function get_menu_navegacion(int $idRol): array
    {
        $modulos = MenuNavData::get_modulos_by_rol($idRol);

        $menu = [];

        foreach ($modulos as $modulo) {
            $submodulos = MenuNavData::get_submodulos_by_rol_and_modulo($idRol, $modulo->id_modulo);

            $submodulosData = [];

            foreach ($submodulos as $submodulo) {
                $secciones = MenuNavData::get_secciones_by_rol_and_submodulo($idRol, $submodulo->id_submodulo);

                $submodulosData[] = [
                    'id_submodulo' => $submodulo->id_submodulo,
                    'nombre' => $submodulo->nombre,
                    'path' => $submodulo->path,
                    'secciones' => array_map(function ($seccion) use ($modulo, $submodulo) {
                        return [
                            'id_seccion' => $seccion->id_seccion,
                            'nombre' => $seccion->nombre,
                            'url' => '/' . $modulo->path . '/' . $submodulo->path . '/' . $seccion->path,
                        ];
                    }, $secciones),
                ];
            }

            $menu[] = [
                'id_modulo' => $modulo->id_modulo,
                'nombre' => $modulo->nombre,
                'path' => $modulo->path,
                'submodulos' => $submodulosData,
            ];
        }

        return ApiResponse::success($menu);
    }
}
