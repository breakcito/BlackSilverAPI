<?php

namespace App\Modules\Cambista;

use App\Models\Cambista;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class CambistaController extends Controller
{
    /**
     * Devuelve la lista completa de cambistas (id + nombre) ordenada por
     * nombre. Pensado para alimentar un `<Select>` del frontend.
     */
    public function listar(): JsonResponse
    {
        return response()->json(
            ApiResponse::success(Cambista::listar(), 'Cambistas')
        );
    }

    /**
     * Crea un cambista. Solo pide `nombre`; los 128 chars los valida el
     * backend para no aceptar basura.
     */
    public function crear(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|min:1|max:128',
        ], [
            'nombre.required' => 'El nombre del cambista es obligatorio',
            'nombre.max' => 'El nombre no puede superar 128 caracteres',
        ]);

        if ($validator->fails()) {
            return response()->json(
                ApiResponse::error($validator->errors()->first()),
                422
            );
        }

        $id = Cambista::insertar((string) $request->input('nombre'));

        return response()->json(
            ApiResponse::success([
                'id_cambista' => $id,
                'nombre' => trim((string) $request->input('nombre')),
            ], 'Cambista registrado')
        );
    }
}
