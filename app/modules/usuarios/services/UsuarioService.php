<?php

namespace App\Modules\Usuarios\Services;

use App\Modules\Usuarios\Models\Usuario;
use App\Shared\Enums\EstadoBase;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class UsuarioService
{
    // Autenticar usuario y generar token JWT.
    public function login(string $usuario, string $password): array
    {
        $user = Usuario::getByUsername($usuario);

        if (! $user) {
            return ApiResponse::error('Credenciales inválidas');
        }

        if (! Hash::check($password, $user->password)) {
            return ApiResponse::error('Credenciales inválidas');
        }

        $infoUsuario = Usuario::getInfoUsuarioById($user->id_usuario);

        if (! $infoUsuario) {
            return ApiResponse::error('Error al obtener información del usuario');
        }

        $usuarioModel = Usuario::find($user->id_usuario);
        $token = JWTAuth::fromUser($usuarioModel, [
            'id_usuario' => $infoUsuario->id_usuario,
            'id_rol' => $infoUsuario->id_rol,
            'id_empleado' => $infoUsuario->id_empleado,
        ]);

        return ApiResponse::success([
            'token' => $token,
            'usuario' => $infoUsuario,
        ]);
    }

    public function validarUsuarioJWT(int $id_usuario): array
    {
        $usuario = Usuario::getInfoUsuarioById($id_usuario);

        if (! $usuario) {
            return ApiResponse::error('Usuario no encontrado');
        }

        if ($usuario->estado != EstadoBase::Activo->value) {
            return ApiResponse::error('Usuario inactivo');
        }

        return ApiResponse::success($usuario);
    }
}
