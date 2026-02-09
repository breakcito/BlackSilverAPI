<?php

namespace App\Modules\Usuarios\Services;

use App\Modules\Usuarios\Models\Usuario;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;


class UsuarioService
{
    // Autenticar usuario y generar token JWT.
    public function login(string $usuario, string $password)
    {
        $user = Usuario::getByUsername($usuario);

        if (!$user) {
            return ApiResponse::error('Credenciales inválidas');
        }

        if (!Hash::check($password, $user->password)) {
            return ApiResponse::error('Credenciales inválidas');
        }

        $infoUsuario = Usuario::getInfoUsuarioById($user->id_usuario);

        if (!$infoUsuario) {
            return ApiResponse::error('Error al obtener información del usuario');
        }

        $usuarioModel = Usuario::find($user->id_usuario);
        $token = JWTAuth::fromUser($usuarioModel, [
            'id_usuario' => $infoUsuario->id_usuario,
            'id_rol' => $infoUsuario->id_rol,
            'id_empleado' => $infoUsuario->id_empleado,
        ]);

        return ApiResponse::array(true, [
            'token' => $token,
            $infoUsuario
        ]);
    }
}
