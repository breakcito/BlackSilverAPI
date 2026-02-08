<?php

namespace App\Modules\Sistema\Application\Usecases;

use App\Enums\EstadoBase;
use App\Modules\Roles\Infraestructure\Models\SeccionRol;
use App\Modules\Usuarios\Infraestructure\Models\Usuario;

/**
 * Caso de uso para obtener el menú de navegación del usuario.
 *
 * Retorna la estructura jerárquica de módulos, submódulos y secciones
 * a las que el usuario tiene acceso según su rol, junto con los permisos
 * (acciones) disponibles en cada sección.
 */
class ObtenerMenuUsuarioUseCase
{
    /**
     * Ejecutar el caso de uso.
     *
     * @return array<int, array<string, mixed>>
     */
    public function execute(Usuario $usuario): array
    {
        // Obtener las secciones a las que el rol tiene acceso
        $seccionesRol = SeccionRol::query()
            ->where('id_rol', $usuario->id_rol)
            ->with([
                'seccion.submodulo.modulo',
                'permisos.accionSistemaSeccion.accionSistema',
            ])
            ->get();

        // Agrupar por módulo -> submódulo -> sección
        $menuData = [];

        foreach ($seccionesRol as $seccionRol) {
            $seccion = $seccionRol->seccion;

            if ($seccion->estado !== EstadoBase::Activo) {
                continue;
            }

            $submodulo = $seccion->submodulo;
            if ($submodulo->estado !== EstadoBase::Activo) {
                continue;
            }

            $modulo = $submodulo->modulo;
            if ($modulo->estado !== EstadoBase::Activo) {
                continue;
            }

            // Obtener acciones permitidas
            $acciones = $seccionRol->permisos->map(function ($permiso) {
                $accion = $permiso->accionSistemaSeccion->accionSistema;

                return [
                    'id' => $accion->id,
                    'nombre' => $accion->nombre,
                    'endpoint' => $accion->endpoint,
                    'es_principal' => $permiso->accionSistemaSeccion->es_principal,
                ];
            })->toArray();

            // Estructura del menú
            if (! isset($menuData[$modulo->id])) {
                $menuData[$modulo->id] = [
                    'id' => $modulo->id,
                    'nombre' => $modulo->nombre,
                    'descripcion' => $modulo->descripcion,
                    'submodulos' => [],
                ];
            }

            if (! isset($menuData[$modulo->id]['submodulos'][$submodulo->id])) {
                $menuData[$modulo->id]['submodulos'][$submodulo->id] = [
                    'id' => $submodulo->id,
                    'nombre' => $submodulo->nombre,
                    'descripcion' => $submodulo->descripcion,
                    'secciones' => [],
                ];
            }

            $menuData[$modulo->id]['submodulos'][$submodulo->id]['secciones'][] = [
                'id' => $seccion->id,
                'nombre' => $seccion->nombre,
                'descripcion' => $seccion->descripcion,
                'url' => $seccion->url,
                'acciones' => $acciones,
            ];
        }

        // Convertir arrays asociativos a arrays indexados
        return collect($menuData)->map(function ($modulo) {
            $modulo['submodulos'] = array_values($modulo['submodulos']);

            return $modulo;
        })->values()->toArray();
    }
}
