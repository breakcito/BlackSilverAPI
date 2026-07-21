<?php

namespace App\Modules\Empresas\Controller;

use App\Modules\Empresas\Service\EmpresasService;
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
            'ruc'              => 'required|string|size:11',
            'razon_social'     => 'required|string|max:128',
            'domicilio_fiscal' => 'nullable|string|max:256',
            'logo'             => 'nullable|image|mimes:jpg,png,jpeg|max:2048',
            'documentos'       => 'nullable|array',
        ], [
            'ruc.required'         => 'El RUC es obligatorio',
            'ruc.size'             => 'El RUC debe tener 11 dígitos',
            'razon_social.required' => 'La razón social es obligatoria',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = EmpresasService::crear_empresa(
            ruc: $request->input('ruc'),
            razon_social: $request->input('razon_social'),
            domicilio_fiscal: $request->input('domicilio_fiscal'),
            logo: $request->file('logo'),
            documentos: $request->file('documentos') ?? [],
        );

        return response()->json($result);
    }

    /**
     * Actualizar logo de empresa
     */
    public function actualizar_logo(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'logo' => 'required|image|mimes:jpg,png,jpeg',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = EmpresasService::actualizar_logo($id, $request->file('logo'));

        return response()->json($result);
    }

    /**
     * Agregar documentos a una empresa (se acumulan)
     */
    public function agregar_documentos(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'documentos'   => 'required|array|min:1',
            'documentos.*' => 'file|max:10240',
        ], [
            'documentos.required' => 'Debes enviar al menos un documento.',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = EmpresasService::agregar_documentos($id, $request->file('documentos'));

        return response()->json($result);
    }

    /**
     * Eliminar un documento específico de una empresa
     */
    public function eliminar_documento(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'path_relativo' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = EmpresasService::eliminar_documento($id, $request->input('path_relativo'));

        return response()->json($result);
    }
}
