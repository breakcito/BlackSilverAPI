<?php

namespace App\Views\Cuentas;

use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CuentasController extends Controller
{
    /**
     * Listar todas las cuentas de usuario
     */
    public function get_cuentas(): JsonResponse
    {
        $result = CuentasService::get_cuentas();
        return response()->json($result);
    }

    /**
     * Listar empleados disponibles (sin cuenta) para el select de registro
     */
    public function get_empleados_sin_cuenta(): JsonResponse
    {
        $result = CuentasService::get_empleados_sin_cuenta();
        return response()->json($result);
    }

    /**
     * Obtener los roles disponibles para asignar
     */
    public function get_roles_disponibles(): JsonResponse
    {
        $result = CuentasService::get_roles_disponibles();
        return response()->json($result);
    }

    /**
     * Registrar una nueva cuenta de usuario
     */
    public function crear_cuenta(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_rol' => 'required|exists:rol,id',
            'id_empleado' => 'required|exists:empleado,id|unique:usuario,id_empleado',
            'username' => 'required|string|max:64|unique:usuario,username',
            'password' => 'required|string|min:6|max:512'
        ], [
            'id_empleado.unique' => 'Este empleado ya tiene una cuenta asignada.',
            'username.unique' => 'El nombre de usuario ya está en uso.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.'
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = CuentasService::crear_cuenta($request->all());
        return response()->json($result);
    }

    /**
     * Actualizar datos de una cuenta existente
     */
    public function actualizar_cuenta(Request $request, int $id_usuario): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_rol' => 'required|exists:rol,id',
            'username' => [
                'required',
                'string',
                'max:64',
                Rule::unique('usuario', 'username')->ignore($id_usuario)
            ],
            'password' => 'nullable|string|min:6|max:512',
            'estado' => 'nullable|string'
        ], [
            'username.unique' => 'El nombre de usuario ya está en uso.',
            'password.min' => 'La nueva contraseña debe tener al menos 6 caracteres.'
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = CuentasService::actualizar_cuenta($id_usuario, $request->all());
        return response()->json($result);
    }

    /**
     * Obtener gestión de empresas vinculadas a un usuario
     */
    public function get_empresas_usuario(int $id_usuario): JsonResponse
    {
        $result = CuentasService::get_gestion_empresas($id_usuario);
        return response()->json($result);
    }

    /**
     * Vincular una empresa a un usuario
     */
    public function vincular_empresa(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_usuario' => 'required|exists:usuario,id',
            'id_empresa' => 'required|exists:empresa,id'
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = CuentasService::vincular_empresa(
            (int)$request->input('id_usuario'), 
            (int)$request->input('id_empresa')
        );
        return response()->json($result);
    }

    /**
     * Desvincular una empresa de un usuario
     */
    public function desvincular_empresa(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_usuario' => 'required|exists:usuario,id',
            'id_empresa' => 'required|exists:empresa,id'
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = CuentasService::desvincular_empresa(
            (int)$request->input('id_usuario'), 
            (int)$request->input('id_empresa')
        );
        return response()->json($result);
    }
}
