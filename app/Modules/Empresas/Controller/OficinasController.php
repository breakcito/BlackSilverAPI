<?php

namespace App\Modules\Empresas\Controller;

use App\Services\OficinasService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class OficinasController extends Controller
{
    /**
     * Crear una nueva oficina
     */
    public function crear_oficina(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_empresa' => 'required|integer',
            'nombre' => 'required|string|max:128',
            'direccion' => 'nullable|string|max:256',
            'es_principal' => 'nullable|boolean',
        ], [
            'id_empresa.required' => 'El id_empresa es obligatorio',
            'nombre.required' => 'El nombre es obligatorio',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = OficinasService::crear_oficina(
            id_empresa: $request->input('id_empresa'),
            nombre: $request->input('nombre'),
            direccion: $request->input('direccion'),
            es_principal: $request->input('es_principal', false)
        );

        return response()->json($result);
    }
}
