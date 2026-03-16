<?php

namespace App\Views\Empleados;

use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmpleadosController
{
    /**
     * Listar empleados
     */
    public function get_empleados(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $id_usuario = $authUser->id_usuario;
        $id_empresa = $request->query('id_empresa') ? (int) $request->query('id_empresa') : null;
        $result = EmpleadosService::get_empleados($id_usuario, $id_empresa);

        return response()->json($result);
    }

    /**
     * Obtener empresas
     */
    public function get_empresas(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $id_usuario = $authUser->id_usuario;
        $result = EmpleadosService::get_empresas($id_usuario);

        return response()->json($result);
    }

    /**
     * Obtener áreas
     */
    public function get_areas(Request $request): JsonResponse
    {
        $result = EmpleadosService::get_areas();

        return response()->json($result);
    }

    /**
     * Obtener cargos por área
     */
    public function get_cargos(Request $request, int $id_area): JsonResponse
    {
        $result = EmpleadosService::get_cargos($id_area);

        return response()->json($result);
    }

    /**
     * Crear empleado
     */
    public function crear_empleado(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_empresa' => 'required|integer',
            'id_cargo' => 'required|integer',
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'dni' => 'nullable|string|max:20',
            'ruc' => 'nullable|string|max:20',
            'carnet_extranjeria' => 'nullable|string|max:20',
            'pasaporte' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'nullable|date',
            'path_foto' => 'nullable|image|mimes:jpg,png,jpeg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $authUser = $request->attributes->get('auth_user');
        $id_usuario = $authUser->id_usuario;

        $result = EmpleadosService::crear_empleado($id_usuario, $request->all());

        return response()->json($result);
    }

    /**
     * Actualizar foto de empleado
     */
    public function actualizar_foto(Request $request, int $id_empleado): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'path_foto' => 'required|image|mimes:jpg,png,jpeg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $authUser = $request->attributes->get('auth_user');
        $id_usuario = $authUser->id_usuario;

        $result = EmpleadosService::actualizar_foto($id_usuario, $id_empleado, $request->file('path_foto'));

        return response()->json($result);
    }
}
