<?php

namespace App\Views\Roles;

use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class RolesController extends Controller
{
    /**
     * Listar todos los roles activos
     */
    public function get_roles(): JsonResponse
    {
        $result = RolesService::get_roles();
        return response()->json($result);
    }

    /**
     * Obtener toda la estructura de modulos, submodulos y secciones
     */
    public function get_estructura_permisos(): JsonResponse
    {
        $result = RolesService::get_estructura_permisos();
        return response()->json($result);
    }

    /**
     * Registrar un nuevo rol con sus permisos
     */
    public function crear_rol(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:64',
            'descripcion' => 'nullable|string|max:512',
            'secciones' => 'required|array|min:1',
            'secciones.*' => 'integer|exists:seccion,id'
        ], [
            'nombre.required' => 'El nombre del rol es obligatorio.',
            'secciones.required' => 'Debe seleccionar al menos una sección.',
            'secciones.*.exists' => 'Una de las secciones seleccionadas no es válida.'
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = RolesService::crear_rol($request->all());
        return response()->json($result);
    }
}
