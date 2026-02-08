<?php

namespace App\Modules\Roles\Application\Usecases;

use App\Enums\EstadoBase;
use App\Modules\Roles\Application\Dtos\CrearRolRequest;
use App\Modules\Roles\Infraestructure\Models\PermisoRolSeccion;
use App\Modules\Roles\Infraestructure\Models\Rol;
use App\Modules\Roles\Infraestructure\Models\SeccionRol;
use Illuminate\Support\Facades\DB;

/**
 * Caso de uso para crear un rol con sus secciones y permisos.
 */
class CrearRolUseCase
{
    /**
     * Ejecutar el caso de uso.
     */
    public function execute(CrearRolRequest $request): Rol
    {
        return DB::transaction(function () use ($request) {
            // Crear el rol
            $rol = Rol::query()->create([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'estado' => EstadoBase::Activo,
            ]);

            // Asignar secciones y permisos
            foreach ($request->secciones as $seccionData) {
                $seccionRol = SeccionRol::query()->create([
                    'id_seccion' => $seccionData['id_seccion'],
                    'id_rol' => $rol->id,
                ]);

                // Asignar acciones (permisos) a la sección
                foreach ($seccionData['acciones'] as $idAccionSistemaSeccion) {
                    PermisoRolSeccion::query()->create([
                        'id_seccion_rol' => $seccionRol->id,
                        'id_accion_sistema_seccion' => $idAccionSistemaSeccion,
                    ]);
                }
            }

            return $rol->load(['seccionesRol.seccion', 'seccionesRol.permisos']);
        });
    }
}
