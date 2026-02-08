<?php

namespace App\Modules\Usuarios\Application\Usecases;

use App\Modules\Usuarios\Application\Dtos\CrearUsuarioRequest;
use App\Modules\Usuarios\Infraestructure\Models\Usuario;

/**
 * Caso de uso para crear un usuario.
 */
class CrearUsuarioUseCase
{
    /**
     * Ejecutar el caso de uso.
     *
     * @throws \InvalidArgumentException Si el username ya existe
     */
    public function execute(CrearUsuarioRequest $request): Usuario
    {
        // Verificar que el username no exista
        $existente = Usuario::query()
            ->where('username', $request->username)
            ->exists();

        if ($existente) {
            throw new \InvalidArgumentException('El username ya está en uso');
        }

        return Usuario::query()->create([
            'id_rol' => $request->idRol,
            'id_empleado' => $request->idEmpleado,
            'username' => $request->username,
            'password' => $request->password,
        ]);
    }
}
