<?php

namespace App\Controllers;

use App\Services\EmpresaService;
use App\Services\UsuarioService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class EmpresaController extends Controller
{
    public function __construct(
        private EmpresaService $empresaService,
        private UsuarioService $usuarioService
    ) {}

    public function get_empresas(Request $request): JsonResponse
    {
        $result = $this->empresaService->get_empresas();

        return response()->json($result);
    }

    public function get_empresas_by_session(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        if (! $authUser || ! isset($authUser->id_rol)) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $result = $this->empresaService->get_empresas_by_usuario($authUser->id_usuario);

        return response()->json($result);
    }

    public function crear_empresa(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ruc' => 'required|string|size:11',
            'razon_social' => 'required|string|max:128',
            'nombre_comercial' => 'required|string|max:128',
            'abreviatura' => 'required|string|max:24',
            'path_logo' => 'required|string|max:256',
        ], [
            'ruc.required' => 'El RUC es requerido',
            'ruc.size' => 'El RUC debe tener 11 dígitos',
            'razon_social.required' => 'La razón social es requerida',
            'nombre_comercial.required' => 'El nombre comercial es requerido',
            'abreviatura.required' => 'La abreviatura es requerida',
            'path_logo.required' => 'El logo es requerido',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $data = $validator->validated();
        $result = $this->empresaService->crear_empresa(
            $data['ruc'],
            $data['razon_social'],
            $data['nombre_comercial'],
            $data['abreviatura'],
            $data['path_logo']
        );

        return response()->json($result);
    }
}
