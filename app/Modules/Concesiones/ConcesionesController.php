<?php

namespace App\Modules\Concesiones;

use App\Shared\Responses\ApiResponse;
use App\Shared\Enums\_Generic\TipoMineral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ConcesionesController
{
    public function get_concesiones(Request $request): JsonResponse
    {
        $result = ConcesionesService::get_concesiones();

        return response()->json($result);
    }

    public function crear_concesion(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'codigo_reinfo' => 'nullable|string|max:100',
            'ubigeo' => 'nullable|string|max:100',
            'tipo_mineral' => ['required', Rule::enum(TipoMineral::class)],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $v = $validator->validated();

        $result = ConcesionesService::crear_concesion(
            nombre: (string) $v['nombre'],
            codigo_reinfo: isset($v['codigo_reinfo']) ? (string) $v['codigo_reinfo'] : null,
            ubigeo: isset($v['ubigeo']) ? (string) $v['ubigeo'] : null,
            tipo_mineral: (string) $v['tipo_mineral']
        );

        return response()->json($result);
    }

    public function get_contratos(Request $request, int $id_concesion): JsonResponse
    {
        $result = ConcesionesService::get_contratos($id_concesion);

        return response()->json($result);
    }

    public function crear_contrato(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_concesion' => 'required|integer',
            'id_empresa' => 'required|integer',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'evidencias' => 'nullable|array',
            'evidencias.*' => 'file|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $v = $validator->validated();

        $result = ConcesionesService::crear_contrato(
            id_concesion: (int) $v['id_concesion'],
            id_empresa: (int) $v['id_empresa'],
            fecha_inicio: (string) $v['fecha_inicio'],
            fecha_fin: isset($v['fecha_fin']) ? (string) $v['fecha_fin'] : null,
            evidencias: $request->file('evidencias') ?? []
        );

        return response()->json($result);
    }

    public function terminar_contrato(Request $request, int $id_contrato): JsonResponse
    {
        $result = ConcesionesService::terminar_contrato($id_contrato);

        return response()->json($result);
    }

    /**
     * Sube y acumula nuevas evidencias a un contrato existente.
     */
    public function subir_evidencias(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_contrato' => 'required|integer',
            'evidencias' => 'required|array|min:1',
            'evidencias.*' => 'file|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $id_contrato = (int) $request->input('id_contrato');
        $evidencias = $request->file('evidencias', []);

        try {
            $resultado = ConcesionesService::subir_evidencias($id_contrato, $evidencias);
            return response()->json($resultado);
        } catch (\Exception $e) {
            return response()->json(ApiResponse::error('Error al subir evidencias: ' . $e->getMessage()), 500);
        }
    }

    /**
     * Elimina una evidencia específica de un contrato por su path_relativo.
     */
    public function eliminar_evidencia(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_contrato' => 'required|integer',
            'path_relativo' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $id_contrato = (int) $request->input('id_contrato');
        $path_relativo = (string) $request->input('path_relativo');

        $resultado = ConcesionesService::eliminar_evidencia($id_contrato, $path_relativo);
        return response()->json($resultado);
    }
}