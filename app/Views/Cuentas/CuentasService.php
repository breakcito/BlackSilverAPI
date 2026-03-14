<?php

namespace App\Views\Cuentas;

use App\Models\Usuario;
use App\Models\UsuarioEmpresa;
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

            // 2. Obtener la empresa a la que pertenece el empleado para el vínculo automático
            $empleado = Empleado::find($data['id_empleado']);
            
            if ($empleado && $empleado->id_empresa) {
                UsuarioEmpresa::create([
                    'id_usuario' => $usuario->id,
                    'id_empresa' => $empleado->id_empresa
                ]);
            }

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

    /**
     * Obtener empresas con acceso y empresas disponibles para vincular
     */
    public static function get_gestion_empresas(int $id_usuario): array
    {
        return [
            'asignadas' => CuentasData::get_empresas_usuario($id_usuario),
            'todas' => CuentasData::get_todas_las_empresas()
        ];
    }

    /**
     * Vincular una empresa a un usuario
     */
    public static function vincular_empresa(int $id_usuario, int $id_empresa)
    {
        if (CuentasData::existe_vinculo_empresa($id_usuario, $id_empresa)) {
            return ApiResponse::error('El usuario ya tiene acceso a esta empresa.');
        }

        UsuarioEmpresa::create([
            'id_usuario' => $id_usuario,
            'id_empresa' => $id_empresa
        ]);

        return ApiResponse::success(null, 'Empresa vinculada correctamente.');
    }

    /**
     * Desvincular una empresa asegurando que siempre quede al menos una
     */
    public static function desvincular_empresa(int $id_usuario, int $id_empresa)
    {
        $conteo = CuentasData::contar_empresas_usuario($id_usuario);
        
        if ($conteo <= 1) {
            return ApiResponse::error('No se puede desvincular. El usuario debe tener al menos una empresa asignada.');
        }

        UsuarioEmpresa::where('id_usuario', $id_usuario)
            ->where('id_empresa', $id_empresa)
            ->delete();

        return ApiResponse::success(null, 'Empresa desvinculada correctamente.');
    }
}
