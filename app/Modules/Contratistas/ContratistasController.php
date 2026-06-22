<?php

namespace App\Modules\Contratistas;

use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Modules\Contratistas\Service\ContratistasService;

class ContratistasController
{
    /**
     * Listar contratistas
     */
    public function get_contratistas(Request $request): JsonResponse
    {
        $id_mina = $request->query('id_mina') ? (int) $request->query('id_mina') : null;
        $result = ContratistasService::get_contratistas($id_mina);

        return response()->json($result);
    }

    /**
     * Crear contratista
     */
    public function crear_contratista(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_mina' => 'required|integer',
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'dni' => 'nullable|string|max:20',
            'ruc' => 'nullable|string|max:20',
            'carnet_extranjeria' => 'nullable|string|max:20',
            'pasaporte' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'nullable|date',
            'foto' => 'nullable|image|mimes:jpg,png,jpeg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = ContratistasService::crear_contratista(
            id_mina: $request->input('id_mina') ? (int) $request->input('id_mina') : null,
            nombre: (string) $request->input('nombre'),
            apellido: (string) $request->input('apellido'),
            dni: $request->input('dni'),
            ruc: $request->input('ruc'),
            carnet_extranjeria: $request->input('carnet_extranjeria'),
            pasaporte: $request->input('pasaporte'),
            fecha_nacimiento: $request->input('fecha_nacimiento'),
            foto: $request->file('foto'),
            ids_labor: (array) $request->input('ids_labor', [])
        );

        return response()->json($result);
    }

    /**
     * Actualizar foto de contratista
     */
    public function actualizar_foto(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'foto' => 'required|image|mimes:jpg,png,jpeg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = ContratistasService::actualizar_foto($id, $request->file('foto'));

        return response()->json($result);
    }

    
    /**
     * Asignar labores a un contratista
     */
    public function asignar_labores(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_mina' => 'required|integer',
            'ids_labor' => 'nullable|array',
            'ids_labor.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = ContratistasService::asignar_labores(
            id_contratista: $id,
            id_mina: $request->input('id_mina') ? (int) $request->input('id_mina') : null,
            ids_labor: (array) $request->input('ids_labor')
        );

        return response()->json($result);
    }
}
