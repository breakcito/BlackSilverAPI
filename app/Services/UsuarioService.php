<?php

namespace App\Services;

use App\Models\Usuario;
use App\Models\UsuarioEmpresa;
use App\Shared\Enums\EstadoBase;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class UsuarioService
{
    // Autenticar usuario y generar token JWT.
    public function login(string $usuario, string $password): array
    {
        // Buscamos al usuario por username y que este activo
        $user = Usuario::where('username', $usuario)
            ->where('estado', EstadoBase::Activo->value)
            ->first(['id', 'password']);

        if (!$user) {
            return ApiResponse::error('Credenciales inválidas');
        }

        // Comparamos las contraseñas
        if (!Hash::check($password, $user->password)) {
            return ApiResponse::error('Credenciales inválidas');
        }

        // Si todo salio bien, obtenemos su informacion
        $infoUsuario = Usuario::getInfoUsuarioById($user->id);
        if (!$infoUsuario) {
            return ApiResponse::error('Error al obtener información del usuario');
        }

        $token = JWTAuth::fromUser($user, [
            'id_usuario' => $infoUsuario->id_usuario,
            'id_rol' => $infoUsuario->id_rol,
            'id_empleado' => $infoUsuario->id_empleado,
        ]);

        return ApiResponse::success([
            'token' => $token,
            'usuario' => $infoUsuario,
        ]);
    }

    public function getInfoUsuarioById(int $id_usuario): array
    {
        $usuario = Usuario::getInfoUsuarioById($id_usuario);

        if (!$usuario) {
            return ApiResponse::error('Usuario no encontrado');
        }

        if ($usuario->estado != EstadoBase::Activo->value) {
            return ApiResponse::error('Usuario inactivo');
        }

        return ApiResponse::success($usuario);
    }

    public function get_usuarios_por_empresa(int $id_empresa)
    {
        $usuarios = UsuarioEmpresa::get_usuarios_por_empresa($id_empresa);

        return ApiResponse::success($usuarios);
    }
}
