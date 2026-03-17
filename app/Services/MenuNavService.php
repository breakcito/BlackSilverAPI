<?php

namespace App\Services;

use App\Shared\Responses\ApiResponse;
use App\Data\MenuNavData;

class MenuNavService
{
    public static function get_menu_navegacion(int $idRol): array
    {
        // 1. Obtener todos los módulos
        $modulos = MenuNavData::get_modulos_by_rol($idRol);
        if (empty($modulos)) return ApiResponse::success([]);

        $idsModulos = array_column($modulos, 'id_modulo');

        // 2. Obtener TODOS los submódulos de esos módulos
        $todosLosSubmodulos = MenuNavData::get_submodulos_by_rol_and_modulos($idRol, $idsModulos);
        $idsSubmodulos = array_column($todosLosSubmodulos, 'id_submodulo');

        // 3. Obtener TODAS las secciones de esos submódulos
        $todasLasSecciones = !empty($idsSubmodulos)
            ? MenuNavData::get_secciones_by_rol_and_submodulos($idRol, $idsSubmodulos)
            : [];

        // AGRUPACIÓN

        // Agrupar secciones por su padre (id_submodulo)
        $seccionesAgrupadas = [];
        foreach ($todasLasSecciones as $seccion) {
            $seccionesAgrupadas[$seccion->id_submodulo][] = $seccion;
        }

        // Agrupar submódulos por su padre (id_modulo)
        $submodulosAgrupados = [];
        foreach ($todosLosSubmodulos as $submodulo) {
            $submodulosAgrupados[$submodulo->id_modulo][] = $submodulo;
        }

        // CONSTRUCCIÓN DE LA ESTRUCTURA

        $menu = [];
        foreach ($modulos as $modulo) {
            $submodulosData = [];

            $misSubmodulos = $submodulosAgrupados[$modulo->id_modulo] ?? [];

            foreach ($misSubmodulos as $submodulo) {
                $misSecciones = $seccionesAgrupadas[$submodulo->id_submodulo] ?? [];

                $submodulosData[] = [
                    'id_submodulo' => $submodulo->id_submodulo,
                    'nombre'       => $submodulo->nombre,
                    'path'         => $submodulo->path,
                    'secciones'    => array_map(function ($seccion) use ($modulo, $submodulo) {
                        return [
                            'id_seccion' => $seccion->id_seccion,
                            'nombre'     => $seccion->nombre,
                            'url'        => "/{$modulo->path}/{$submodulo->path}/{$seccion->path}",
                        ];
                    }, $misSecciones),
                ];
            }

            $menu[] = [
                'id_modulo'  => $modulo->id_modulo,
                'nombre'     => $modulo->nombre,
                'path'       => $modulo->path,
                'submodulos' => $submodulosData,
            ];
        }

        return ApiResponse::success($menu);
    }
}
