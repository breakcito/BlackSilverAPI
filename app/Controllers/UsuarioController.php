<?php

namespace App\Controllers;

use App\Services\UsuarioService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class UsuarioController extends Controller
{
    public function __construct(
        private UsuarioService $usuarioService
    ) {}

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'usuario' => 'required|string',
            'password' => 'required|string',
        ], [
            'usuario.required' => 'El usuario es requerido',
            'password.required' => 'La contraseña es requerida',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = $this->usuarioService->login(
            $request->input('usuario'),
            $request->input('password')
        );

        return response()->json($result);
    }

    public function get_usuarios_por_empresa(Request $request): JsonResponse
    {
        $id_empresa = $request->query('id_empresa');
        if (!$id_empresa) {
            return response()->json(ApiResponse::error('El id_empresa es requerido'));
        }

        $result = $this->usuarioService->get_usuarios_por_empresa((int) $id_empresa);

        return response()->json($result);
    }
}
