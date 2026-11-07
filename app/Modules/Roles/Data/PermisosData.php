<?php

namespace App\Modules\Roles\Data;

use App\Models\Menu;
use App\Models\Submenu;
use App\Models\Modulo;
use App\Models\ModuloRol;

class PermisosData
{
    /**
     * Asigna un permiso a un rol a cualquier nivel (menu|submenu|modulo).
     * Valida que el nodo exista y que no sea un contenedor (no se otorgan
     * permisos a contenedores — los permisos se otorgan a las hojas).
     *
     * Los MODULOS son siempre hojas: la tabla `modulo` no tiene columna
     * `es_desplegable`, asi que el flag solo se consulta en menu y submenu.
     */
    public static function asignar_permiso_a_rol(int $id_rol, int $id_nodo, string $tipo_nodo): void
    {
        if (!in_array($tipo_nodo, ['menu', 'submenu', 'modulo'], true)) {
            throw new \InvalidArgumentException("tipo_nodo debe ser menu|submenu|modulo");
        }

        $existe = match ($tipo_nodo) {
            'menu'    => Menu::where('id', $id_nodo)->exists(),
            'submenu' => Submenu::where('id', $id_nodo)->exists(),
            'modulo'  => Modulo::where('id', $id_nodo)->exists(),
        };

        if (!$existe) {
            throw new \DomainException("El nodo indicado no existe.");
        }

        $esDesplegable = false;
        if ($tipo_nodo === 'menu') {
            $esDesplegable = (bool) Menu::where('id', $id_nodo)->value('es_desplegable');
        } elseif ($tipo_nodo === 'submenu') {
            $esDesplegable = (bool) Submenu::where('id', $id_nodo)->value('es_desplegable');
        }

        if ($esDesplegable) {
            throw new \DomainException("No se puede asignar permiso a un nodo desplegable (es contenedor)");
        }

        $col = match ($tipo_nodo) {
            'menu'    => 'id_menu',
            'submenu' => 'id_submenu',
            'modulo'  => 'id_modulo',
        };

        // Evita duplicados: si el permiso ya existe para ese nodo y rol, no se inserta otra vez.
        if (ModuloRol::where('id_rol', $id_rol)->where($col, $id_nodo)->exists()) {
            return;
        }

        ModuloRol::create(['id_rol' => $id_rol, $col => $id_nodo]);
    }

    /** Compatibilidad: delega a asignar_permiso_a_rol. */
    public static function asignar_modulo_a_rol(int $id_rol, int $id_modulo): void
    {
        self::asignar_permiso_a_rol($id_rol, $id_modulo, 'modulo');
    }

    /**
     * Elimina todas las filas que tengan exactamente el FK del nodo dado.
     */
    public static function eliminar_permiso_de_rol(int $id_rol, int $id_nodo, string $tipo_nodo): void
    {
        $col = match ($tipo_nodo) {
            'menu'    => 'id_menu',
            'submenu' => 'id_submenu',
            'modulo'  => 'id_modulo',
        };
        ModuloRol::where('id_rol', $id_rol)->where($col, $id_nodo)->delete();
    }

    public static function eliminar_modulo_de_rol(int $id_rol, int $id_modulo): void
    {
        self::eliminar_permiso_de_rol($id_rol, $id_modulo, 'modulo');
    }

    /**
     * Devuelve los IDs de modulos asignados al rol (compatibilidad legacy).
     */
    public static function get_ids_modulos_por_rol(int $id_rol): array
    {
        return ModuloRol::where('id_rol', $id_rol)
            ->whereNotNull('id_modulo')
            ->pluck('id_modulo')
            ->toArray();
    }

    /**
     * Devuelve todos los permisos del rol, normalizados a
     * `[{tipo:'menu|submenu|modulo', id:int}, ...]`.
     */
    public static function get_permisos_por_rol(int $id_rol): array
    {
        $rows = ModuloRol::where('id_rol', $id_rol)->get();
        $out = [];
        foreach ($rows as $r) {
            if ($r->id_menu)    $out[] = ['tipo' => 'menu',    'id' => (int) $r->id_menu];
            if ($r->id_submenu) $out[] = ['tipo' => 'submenu', 'id' => (int) $r->id_submenu];
            if ($r->id_modulo)  $out[] = ['tipo' => 'modulo',  'id' => (int) $r->id_modulo];
        }
        return $out;
    }

    /**
     * Estructura completa menu -> submenu -> modulo para el selector de
     * permisos. Incluye es_desplegable en menu y submenu; los modulos son
     * siempre hojas y no lo exponen.
     */
    public static function get_estructura_permisos()
    {
        $menus = Menu::where('estado', 'Activo')
            ->orderBy('numero_orden')
            ->get(['id', 'nombre', 'path', 'numero_orden', 'es_desplegable', 'estado']);

        foreach ($menus as $menu) {
            $submenus = Submenu::where('id_menu', $menu->id)
                ->where('estado', 'Activo')
                ->orderBy('numero_orden')
                ->get(['id', 'id_menu', 'nombre', 'path', 'numero_orden', 'es_desplegable', 'estado']);

            foreach ($submenus as $submenu) {
                $submenu->modulos = Modulo::where('id_submenu', $submenu->id)
                    ->where('estado', 'Activo')
                    ->orderBy('numero_orden')
                    ->get(['id', 'id_submenu', 'nombre', 'path', 'numero_orden', 'estado']);
            }

            $menu->submenus = $submenus;
        }

        return $menus;
    }

    public static function limpiar_permisos_rol(int $id_rol): void
    {
        ModuloRol::where('id_rol', $id_rol)->delete();
    }
}