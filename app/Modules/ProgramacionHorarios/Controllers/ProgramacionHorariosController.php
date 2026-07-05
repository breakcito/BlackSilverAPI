<?php

namespace App\Modules\ProgramacionHorarios\Controllers;

use App\Modules\ProgramacionHorarios\Services\ProgramacionHorarioService;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProgramacionHorariosController
{
    /**
     * Listar programaciones con filtros opcionales.
     */
    public function get_programaciones(Request $request): JsonResponse
    {
        $estado_val = $request->query('estado');
        $estado = $estado_val ? EstadoBase::from($estado_val) : null;

        $result = ProgramacionHorarioService::get_programaciones(
            id_empleado: $request->query('id_empleado') ? (int) $request->query('id_empleado') : null,
            id_turno_laboral: $request->query('id_turno_laboral') ? (int) $request->query('id_turno_laboral') : null,
            estado: $estado,
            fecha_desde: $request->query('fecha_desde'),
            fecha_hasta: $request->query('fecha_hasta'),
        );

        return response()->json($result);
    }

    /**
     * Ver una programación por id.
     */
    public function get_programacion_by_id(Request $request, int $id_programacion): JsonResponse
    {
        return response()->json(ProgramacionHorarioService::get_programacion_by_id($id_programacion));
    }

    /**
     * Obtener programaciones que se solapan con el rango semanal indicado.
     * El frontend recibe la grilla y arma la vista semanal en cliente.
     */
    public function get_grilla_semanal(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'fecha_inicio' => 'required|date_format:Y-m-d',
            'fecha_fin' => 'required|date_format:Y-m-d|after_or_equal:fecha_inicio',
        ], [
            'fecha_inicio.required' => 'La fecha de inicio de la semana es obligatoria.',
            'fecha_fin.required' => 'La fecha de fin de la semana es obligatoria.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        return response()->json(ProgramacionHorarioService::get_grilla_semanal(
            (string) $request->query('fecha_inicio'),
            (string) $request->query('fecha_fin'),
        ));
    }

    /**
     * Asignar horario a uno o varios empleados (soporta ingreso masivo).
     *
     * @param  array  $payload  ['id_turno_laboral', 'fecha_inicio', 'por_tiempo_indefinido', 'fecha_fin', 'dias_laborables', 'empleados' => [int,...]]
     */
    public function asignar_horario(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_turno_laboral' => 'required|integer|min:1',
            'fecha_inicio' => 'required|date_format:Y-m-d',
            'por_tiempo_indefinido' => 'nullable|boolean',
            'fecha_fin' => 'nullable|date_format:Y-m-d',
            'dias_laborables' => 'required|string|size:7|regex:/^[01]{7}$/',
            'empleados' => 'required|array|min:1',
            'empleados.*' => 'integer|min:1',
        ], [
            'id_turno_laboral.required' => 'Debe seleccionar un turno laboral.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'dias_laborables.required' => 'El patrón de días laborables es obligatorio.',
            'dias_laborables.size' => 'El patrón debe tener exactamente 7 caracteres.',
            'dias_laborables.regex' => 'El patrón solo puede contener 0 o 1 (Domingo a Sábado).',
            'empleados.required' => 'Debe seleccionar al menos un empleado.',
            'empleados.array' => 'La lista de empleados es inválida.',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $payload = [
            'id_turno_laboral' => (int) $request->input('id_turno_laboral'),
            'fecha_inicio' => (string) $request->input('fecha_inicio'),
            'por_tiempo_indefinido' => (bool) $request->boolean('por_tiempo_indefinido'),
            'fecha_fin' => $request->input('fecha_fin'),
            'dias_laborables' => (string) $request->input('dias_laborables'),
            'empleados' => $request->input('empleados'),
        ];

        return response()->json(ProgramacionHorarioService::asignar_horario($payload));
    }

    /**
     * Cambiar estado (Activo/Inactivo) de una programación.
     */
    public function cambiar_estado(Request $request, int $id_programacion): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'estado' => 'required|in:Activo,Inactivo',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        return response()->json(ProgramacionHorarioService::cambiar_estado(
            $id_programacion,
            (string) $request->input('estado')
        ));
    }
}
