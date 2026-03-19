<?php

namespace App\Views\Cuentas;

use App\Models\Usuario;
use App\Models\Empleado;
use App\Views\Cuentas\Data\CuentasData;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class CuentasService
{
    public static function get_cuentas(): array
    {
        return CuentasData::get_cuentas();
    }

    public static function get_empleados_sin_cuenta(): array
    {
        return CuentasData::get_empleados_sin_cuenta();
    }

    public static function get_roles_disponibles(): array
    {
        return CuentasData::get_roles_disponibles();
    }

    /**
     * Crear una nueva cuenta de usuario y vincularla a su empresa principal
     */
    public static function crear_cuenta(array $data)
    {
        return DB::transaction(function () use ($data) {
            // 1. Crear el usuario
            $usuario = Usuario::create([
                'id_rol' => $data['id_rol'],
                'id_empleado' => $data['id_empleado'],
                'username' => $data['username'],
                'password' => Hash::make($data['password']),
                'estado' => 'Activo'
            ]);

            return ApiResponse::success($usuario, 'Cuenta creada correctamente.');
        });
    }

    /**
     * Actualizar datos de la cuenta (incluyendo contraseña opcional)
     */
    public static function actualizar_cuenta(int $id_usuario, array $data)
    {
        $usuario = Usuario::find($id_usuario);
        if (!$usuario) return ApiResponse::error('Usuario no encontrado.');

        $updateData = [
            'id_rol' => $data['id_rol'],
            'username' => $data['username']
        ];

        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        if (isset($data['estado'])) {
            $updateData['estado'] = $data['estado'];
        }

        $usuario->update($updateData);

        return ApiResponse::success(null, 'Cuenta actualizada correctamente.');
    }
}
