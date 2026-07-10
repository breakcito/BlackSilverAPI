<?php

namespace App\Modules\ProgramacionHorarios\Services;

use App\Modules\ProgramacionHorarios\Data\TurnoLaboralData;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\ProgramacionHorario\TipoTurno;
use App\Shared\Responses\ApiResponse;

class TurnoLaboralService
{
    /**
     * Listar turnos con filtros.
     */
    public static function get_turnos(
        ?EstadoBase $estado = null,
        ?TipoTurno $tipo_turno = null,
    ): array {
        $data = TurnoLaboralData::get_turnos(estado: $estado, tipo_turno: $tipo_turno);

        return ApiResponse::success($data);
    }

    /**
     * Ver un turno por id.
     */
    public static function get_turno_by_id(int $id_turno): array
    {
        $data = TurnoLaboralData::get_turnos(id_turno: $id_turno);

        return ApiResponse::success($data);
    }

    /**
     * Registrar un turno laboral.
     */
    public static function crear_turno(
        string $tipo_turno,
        string $hora_ingreso,
        string $hora_salida,
        ?int $minutos_tolerancia = null,
        ?float $total_horas = null,
        ?string $estado = null,
    ): array {
        $tipo_enum = TipoTurno::tryFrom($tipo_turno);
        if ($tipo_enum === null) {
            return ApiResponse::error('Tipo de turno inválido.');
        }

        $estado_final = $estado ?? EstadoBase::Activo->value;

        $payload = [
            'tipo_turno' => $tipo_enum->value,
            'hora_ingreso' => $hora_ingreso,
            'hora_salida' => $hora_salida,
            'minutos_tolerancia' => $minutos_tolerancia,
            'total_horas' => $total_horas,
            'estado' => $estado_final,
        ];

        $id = TurnoLaboralData::crear_turno($payload);
        $nuevo = TurnoLaboralData::get_turnos(id_turno: $id);

        return ApiResponse::success($nuevo, 'Turno registrado correctamente');
    }

    /**
     * Actualizar un turno laboral existente.
     */
    public static function actualizar_turno(
        int $id_turno,
        ?string $tipo_turno = null,
        ?string $hora_ingreso = null,
        ?string $hora_salida = null,
        ?int $minutos_tolerancia = null,
        ?float $total_horas = null,
    ): array {
        $payload = [];

        if ($tipo_turno !== null) {
            $tipo_enum = TipoTurno::tryFrom($tipo_turno);
            if ($tipo_enum === null) {
                return ApiResponse::error('Tipo de turno inválido.');
            }
            $payload['tipo_turno'] = $tipo_enum->value;
        }

        if ($hora_ingreso !== null) {
            $payload['hora_ingreso'] = $hora_ingreso;
        }

        if ($hora_salida !== null) {
            $payload['hora_salida'] = $hora_salida;
        }

        if ($minutos_tolerancia !== null) {
            $payload['minutos_tolerancia'] = $minutos_tolerancia;
        }

        if ($total_horas !== null) {
            $payload['total_horas'] = $total_horas;
        }

        if (empty($payload)) {
            return ApiResponse::error('No se proporcionaron campos para actualizar.');
        }

        TurnoLaboralData::actualizar_turno($id_turno, $payload);
        $actualizado = TurnoLaboralData::get_turnos(id_turno: $id_turno);

        return ApiResponse::success($actualizado, 'Turno actualizado correctamente');
    }

    /**
     * Cambiar estado Activo/Inactivo de un turno.
     */
    public static function cambiar_estado(int $id_turno, string $estado): array
    {
        TurnoLaboralData::cambiar_estado($id_turno, $estado);

        return ApiResponse::success(null, 'Estado del turno actualizado');
    }
}
