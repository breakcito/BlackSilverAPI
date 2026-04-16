<?php

namespace App\Modules\MinasLabores\Controller;

use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use App\Modules\MinasLabores\Service\LaboresService;

class LaboresController extends Controller
{

    public function get_tipos_labor(Request $request): JsonResponse
    {
        return response()->json(LaboresService::get_tipos_labor());
    }

    public function get_labores(Request $request): JsonResponse
    {
        $id_mina = $request->query('id_mina');
        if (! $id_mina) {
            return response()->json(ApiResponse::error('id_mina es requerido'), 400);
        }

        return response()->json(LaboresService::get_labores((int) $id_mina));
    }

    public function crear_labor(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_mina' => 'required|integer',
            'id_empresa' => 'required|integer',
            'id_tipo_labor' => 'required|integer',
            'nombre' => 'nullable|string|max:128',
            'descripcion' => 'nullable|string',
            'tipo_sostenimiento' => 'required|string',
            'veta' => 'nullable|string|max:128',
            'ancho' => 'nullable|numeric',
            'alto' => 'nullable|numeric',
            'nivel' => 'nullable|string|max:64',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin_estimada' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $v = $validator->validated();

        return response()->json(LaboresService::crear_labor(
            id_mina: (int) $v['id_mina'],
            id_empresa: (int) $v['id_empresa'],
            id_tipo_labor: (int) $v['id_tipo_labor'],
            nombre: isset($v['nombre']) ? (string) $v['nombre'] : null,
            descripcion: isset($v['descripcion']) ? (string) $v['descripcion'] : null,
            tipo_sostenimiento: (string) $v['tipo_sostenimiento'],
            veta: isset($v['veta']) ? (string) $v['veta'] : null,
            ancho: isset($v['ancho']) ? (float) $v['ancho'] : null,
            alto: isset($v['alto']) ? (float) $v['alto'] : null,
            nivel: isset($v['nivel']) ? (string) $v['nivel'] : null,
            fecha_inicio: isset($v['fecha_inicio']) ? (string) $v['fecha_inicio'] : null,
            fecha_fin_estimada: isset($v['fecha_fin_estimada']) ? (string) $v['fecha_fin_estimada'] : null,
        ));
    }

    public function finalizar_labor(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_labor' => 'required|integer',
            'fecha_cierre' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $v = $validator->validated();

        return response()->json(LaboresService::finalizar_labor(
            id_labor: (int) $v['id_labor'],
            fecha_cierre: (string) $v['fecha_cierre']
        ));
    }
}
