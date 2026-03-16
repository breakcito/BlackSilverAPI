<?php

namespace App\Views\Empresas;

use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class EmpresasController extends Controller
{
    /**
     * Listar empresas
     */
    public function get_empresas(Request $request): JsonResponse
    {
        $result = EmpresasService::get_empresas();

        return response()->json($result);
    }

    /**
     * Crear una nueva empresa
     */
    public function crear_empresa(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ruc' => 'required|string|size:11',
            'razon_social' => 'required|string|max:128',
            'nombre_comercial' => 'required|string|max:128',
            'abreviatura' => 'nullable|string|max:24',
            'path_logo' => 'nullable|image|mimes:jpg,png,jpeg|max:2048',
        ], [
            'ruc.required' => 'El RUC es obligatorio',
            'ruc.size' => 'El RUC debe tener 11 dígitos',
            'razon_social.required' => 'La razón social es obligatoria',
            'nombre_comercial.required' => 'El nombre comercial es obligatorio',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = EmpresasService::crear_empresa($request->all());

        return response()->json($result);
    }
}
