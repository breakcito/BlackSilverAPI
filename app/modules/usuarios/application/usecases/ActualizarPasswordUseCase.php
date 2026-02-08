<?php

namespace App\Modules\Usuarios\Application\Usecases;

use App\Modules\Usuarios\Infraestructure\Models\Usuario;
use Illuminate\Support\Facades\Hash;

/**
 * Caso de uso para actualizar la contraseña de un usuario.
 */
class ActualizarPasswordUseCase
{
    /**
     * Ejecutar el caso de uso.
     *
     * @throws \InvalidArgumentException Si la contraseña actual es incorrecta
     */
    public function execute(Usuario $usuario, string $passwordActual, string $passwordNuevo): bool
    {
        // Verificar contraseña actual
        if (! Hash::check($passwordActual, $usuario->password)) {
            throw new \InvalidArgumentException('La contraseña actual es incorrecta');
        }

        $usuario->password = $passwordNuevo;
        $usuario->save();

        return true;
    }
}
