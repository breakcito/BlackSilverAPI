<?php

namespace App\Modules\Asistencia\Controllers;

use App\Modules\Asistencia\Services\AsistenciaService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

/**
 * Endpoints administrativos del módulo Asistencia.
 *
 * Protegidos por `auth.jwt.custom`. Usados por la vista /recursos-humanos/
 * control-personal/asistencia.
 */
class AsistenciaController extends Controller
{
    /**
     * Listado de asistencias con filtros (mes, year, id_empleado, q, lugar).
     */
    public function get_asistencias(Request $request): JsonResponse
    {
        $filtros = [
            'mes' => $request->query('mes'),
            'year' => $request->query('year'),
            'id_empleado' => $request->query('id_empleado'),
            'id_almacen' => $request->query('id_almacen'),
            'id_labor' => $request->query('id_labor'),
            'id_lugar' => $request->query('id_lugar'),
            'tipo_lugar' => $request->query('tipo_lugar'),
            'q' => $request->query('q'),
        ];

        return response()->json(AsistenciaService::get_asistencias($filtros));
    }

    /**
     * Detalle de una asistencia específica (incluye sus marcajes).
     */
    public function get_asistencia_by_id(Request $request, int $id_asistencia): JsonResponse
    {
        return response()->json(AsistenciaService::get_asistencia_by_id($id_asistencia));
    }

    /**
     * Registra un marcaje manual desde el panel admin (cuando el empleado
     * olvidó marcar o no pudo completar el flujo del QR).
     */
    public function registrar_marcaje_manual(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_empleado' => 'required|integer|min:1',
            'fecha_hora' => 'required|date',
            'tipo_marcaje' => 'required|in:Ingreso,Salida',
            'id_programacion_horario' => 'nullable|integer|min:1',
            'observaciones' => 'nullable|string|max:500',
        ], [
            'id_empleado.required' => 'Debe seleccionar un empleado.',
            'id_empleado.integer' => 'El empleado seleccionado no es válido.',
            'fecha_hora.required' => 'La fecha y hora son obligatorias.',
            'fecha_hora.date' => 'La fecha y hora no tienen un formato válido.',
            'tipo_marcaje.required' => 'Debe indicar el tipo de marcaje.',
            'tipo_marcaje.in' => 'El tipo de marcaje debe ser Ingreso o Salida.',
            'observaciones.max' => 'Las observaciones no pueden superar los 500 caracteres.',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $authUser = $request->attributes->get('auth_user');
        $id_empleado_registro = $authUser->id_empleado ?? null;

        $result = AsistenciaService::registrar_marcaje_manual(
            $request->only(['id_empleado', 'fecha_hora', 'tipo_marcaje', 'id_programacion_horario', 'observaciones']),
            $id_empleado_registro,
        );

        return response()->json($result);
    }

    /**
     * Cálculo de planilla en vivo (modo agregado por empleado del mes).
     */
    public function calcular_planilla(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'mes' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'id_empleado' => 'nullable|integer|min:1',
        ], [
            'mes.required' => 'El mes es obligatorio.',
            'year.required' => 'El año es obligatorio.',
            'mes.integer' => 'El mes debe ser un número entre 1 y 12.',
            'year.integer' => 'El año debe ser un número válido.',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        return response()->json(AsistenciaService::calcular_planilla([
            'mes' => $request->query('mes'),
            'year' => $request->query('year'),
            'id_empleado' => $request->query('id_empleado'),
        ]));
    }
}
