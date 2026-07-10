<?php

namespace App\Modules\ProgramacionHorarios\Controllers;

use App\Modules\ProgramacionHorarios\Services\TurnoLaboralService;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\ProgramacionHorario\TipoTurno;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TurnoLaboralController
{
    /**
     * Listar turnos con filtros opcionales.
     */
    public function get_turnos(Request $request): JsonResponse
    {
        $estado_val = $request->query('estado');
        $estado = $estado_val ? EstadoBase::from($estado_val) : null;

        $tipo_val = $request->query('tipo_turno');
        $tipo = $tipo_val ? TipoTurno::from($tipo_val) : null;

        return response()->json(TurnoLaboralService::get_turnos(
            estado: $estado,
            tipo_turno: $tipo,
        ));
    }

    /**
     * Ver un turno por id.
     */
    public function get_turno_by_id(Request $request, int $id_turno): JsonResponse
    {
        return response()->json(TurnoLaboralService::get_turno_by_id($id_turno));
    }

    /**
     * Registrar un nuevo turno laboral.
     */
    public function crear_turno(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tipo_turno' => 'required|in:Dia,Noche',
            'hora_ingreso' => 'required|date_format:H:i,H:i:s',
            'hora_salida' => 'required|date_format:H:i,H:i:s',
            'minutos_tolerancia' => 'nullable|integer|min:0|max:1440',
            'total_horas' => 'required|numeric|min:0|max:48',
            'estado' => 'nullable|in:Activo,Inactivo',
        ], [
            'tipo_turno.required' => 'Debe indicar el tipo de turno (Día o Noche).',
            'tipo_turno.in' => 'El tipo de turno debe ser "Dia" o "Noche".',
            'hora_ingreso.required' => 'La hora de ingreso es obligatoria.',
            'hora_ingreso.date_format' => 'La hora de ingreso debe tener formato HH:mm.',
            'hora_salida.required' => 'La hora de salida es obligatoria.',
            'hora_salida.date_format' => 'La hora de salida debe tener formato HH:mm.',
            'minutos_tolerancia.integer' => 'Los minutos de tolerancia deben ser un número entero.',
            'minutos_tolerancia.min' => 'Los minutos de tolerancia no pueden ser negativos.',
            'minutos_tolerancia.max' => 'Los minutos de tolerancia no pueden superar un día.',
            'total_horas.required' => 'Las horas totales son obligatorias.',
            'total_horas.numeric' => 'Las horas totales deben ser un número.',
            'total_horas.min' => 'Las horas totales no pueden ser negativas.',
            'total_horas.max' => 'Las horas totales no pueden superar 48h.',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = TurnoLaboralService::crear_turno(
            tipo_turno: (string) $request->input('tipo_turno'),
            hora_ingreso: (string) $request->input('hora_ingreso'),
            hora_salida: (string) $request->input('hora_salida'),
            minutos_tolerancia: $request->input('minutos_tolerancia') !== null
                ? (int) $request->input('minutos_tolerancia')
                : null,
            total_horas: (float) $request->input('total_horas'),
            estado: $request->input('estado'),
        );

        return response()->json($result);
    }

    /**
     * Actualizar un turno laboral.
     */
    public function actualizar_turno(Request $request, int $id_turno): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tipo_turno' => 'sometimes|in:Dia,Noche',
            'hora_ingreso' => 'sometimes|date_format:H:i,H:i:s',
            'hora_salida' => 'sometimes|date_format:H:i,H:i:s',
            'minutos_tolerancia' => 'sometimes|nullable|integer|min:0|max:1440',
            'total_horas' => 'sometimes|numeric|min:0|max:48',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $minutos = $request->has('minutos_tolerancia')
            ? ($request->input('minutos_tolerancia') !== null ? (int) $request->input('minutos_tolerancia') : null)
            : null;

        $totalHoras = $request->has('total_horas') && $request->input('total_horas') !== null
            ? (float) $request->input('total_horas')
            : null;

        $result = TurnoLaboralService::actualizar_turno(
            id_turno: $id_turno,
            tipo_turno: $request->input('tipo_turno'),
            hora_ingreso: $request->input('hora_ingreso'),
            hora_salida: $request->input('hora_salida'),
            minutos_tolerancia: $minutos,
            total_horas: $totalHoras,
        );

        return response()->json($result);
    }

    /**
     * Cambiar estado (Activo/Inactivo) de un turno.
     */
    public function cambiar_estado(Request $request, int $id_turno): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'estado' => 'required|in:Activo,Inactivo',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        return response()->json(TurnoLaboralService::cambiar_estado(
            $id_turno,
            (string) $request->input('estado')
        ));
    }
}
