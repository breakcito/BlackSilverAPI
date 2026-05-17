<?php

namespace App\Modules\ControlUso\Controller;

use App\Modules\ControlUso\Service\ControlUsoService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

/**
 * Controlador de API para la gestión de registros de uso de activos fijos.
 */
class ControlUsoController extends Controller
{
    /**
     * Obtener el listado de logs de uso.
     */
    public function get_logs(Request $request): JsonResponse
    {
        $tipo_control = $request->input('tipo_control', 'horometro');
        $mes = $request->input('mes') ? (int) $request->input('mes') : null;
        $anio = $request->input('anio') ? (int) $request->input('anio') : null;

        $res = ControlUsoService::get_logs($tipo_control, $mes, $anio);
        return response()->json($res);
    }

    /**
     * Obtener la última lectura para un activo fijo específico.
     */
    public function get_ultimo_horometro(Request $request, int $id_activo_fijo): JsonResponse
    {
        $res = ControlUsoService::get_ultimo_horometro($id_activo_fijo);
        return response()->json($res);
    }

    /**
     * Registrar un nuevo control de uso.
     */
    public function registrar_uso(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_activo_fijo' => 'required|integer',
            'fecha_hora_inicio_control' => 'required|date',
            'fecha_hora_fin_control' => 'nullable|date|after_or_equal:fecha_hora_inicio_control',
            'horometro_inicio' => 'required|numeric|min:0',
            'horometro_fin' => 'required|numeric|min:0|gte:horometro_inicio',
            'precio_unitario' => 'nullable|numeric|min:0',
            'observacion' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $v = $validator->validated();

        $res = ControlUsoService::registrar_uso(
            id_activo_fijo: (int) $v['id_activo_fijo'],
            fecha_hora_inicio_control: (string) $v['fecha_hora_inicio_control'],
            fecha_hora_fin_control: isset($v['fecha_hora_fin_control']) ? (string) $v['fecha_hora_fin_control'] : null,
            horometro_inicio: (float) $v['horometro_inicio'],
            horometro_fin: (float) $v['horometro_fin'],
            precio_unitario: isset($v['precio_unitario']) ? (float) $v['precio_unitario'] : 0.0,
            observacion: isset($v['observacion']) ? (string) $v['observacion'] : null
        );

        return response()->json($res);
    }
}
