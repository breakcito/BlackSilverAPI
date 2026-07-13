<?php

namespace App\Modules\ProgramacionHorarios\Services;

use App\Modules\ProgramacionHorarios\Data\ProgramacionHorarioData;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class ProgramacionHorarioService
{
    /**
     * Listar programaciones con filtros.
     */
    public static function get_programaciones(
        ?int $id_empleado = null,
        ?int $id_turno_laboral = null,
        ?EstadoBase $estado = null,
        ?string $fecha_desde = null,
        ?string $fecha_hasta = null,
        ?int $id_almacen = null,
        ?int $id_labor = null,
        ?int $id_oficina = null,
    ): array {
        $data = ProgramacionHorarioData::get_programaciones(
            id_empleado: $id_empleado,
            id_turno_laboral: $id_turno_laboral,
            estado: $estado,
            fecha_desde: $fecha_desde,
            fecha_hasta: $fecha_hasta,
            id_almacen: $id_almacen,
            id_labor: $id_labor,
            id_oficina: $id_oficina,
        );

        return ApiResponse::success($data);
    }

    /**
     * Ver una programación por id.
     */
    public static function get_programacion_by_id(int $id_programacion): array
    {
        $data = ProgramacionHorarioData::get_programaciones(id_programacion: $id_programacion);

        return ApiResponse::success($data);
    }

    /**
     * Grilla semanal: programaciones que se solapan con el rango indicado.
     */
    public static function get_grilla_semanal(
        string $fecha_inicio_semana,
        string $fecha_fin_semana,
        ?int $id_almacen = null,
        ?int $id_labor = null,
        ?int $id_oficina = null,
    ): array {
        $data = ProgramacionHorarioData::get_programaciones(
            fecha_desde: $fecha_inicio_semana,
            fecha_hasta: $fecha_fin_semana,
            id_almacen: $id_almacen,
            id_labor: $id_labor,
            id_oficina: $id_oficina,
        );

        return ApiResponse::success($data, 'Grilla semanal obtenida correctamente');
    }

    /**
     * Asignar horario a uno o varios empleados.
     *
     * @param  array  $payload  Datos comunes (id_turno_laboral, fecha_inicio, por_tiempo_indefinido, fecha_fin, dias_laborables, empleados[], id_oficina?, id_almacen?, id_labor?)
     */
    public static function asignar_horario(array $payload): array
    {
        $id_turno_laboral = (int) ($payload['id_turno_laboral'] ?? 0);
        $fecha_inicio = (string) ($payload['fecha_inicio'] ?? '');
        $por_tiempo_indefinido = (bool) ($payload['por_tiempo_indefinido'] ?? false);
        $fecha_fin = $payload['fecha_fin'] ?? null;
        $dias_laborables = (string) ($payload['dias_laborables'] ?? '');
        $empleados = $payload['empleados'] ?? [];
        $id_oficina = isset($payload['id_oficina']) && $payload['id_oficina'] !== null
            ? (int) $payload['id_oficina']
            : null;
        $id_almacen = isset($payload['id_almacen']) && $payload['id_almacen'] !== null
            ? (int) $payload['id_almacen']
            : null;
        $id_labor = isset($payload['id_labor']) && $payload['id_labor'] !== null
            ? (int) $payload['id_labor']
            : null;

        if ($id_turno_laboral <= 0) {
            return ApiResponse::error('Debe seleccionar un turno laboral.');
        }

        if (empty($empleados) || ! is_array($empleados)) {
            return ApiResponse::error('Debe seleccionar al menos un empleado.');
        }

        if (! preg_match('/^[01]{7}$/', $dias_laborables)) {
            return ApiResponse::error('El patrón de días laborables es inválido (debe ser 7 caracteres 0/1).');
        }

        if ($fecha_inicio === '') {
            return ApiResponse::error('La fecha de inicio es obligatoria.');
        }

        if (! $por_tiempo_indefinido && (empty($fecha_fin) || $fecha_fin < $fecha_inicio)) {
            return ApiResponse::error('Si el horario no es por tiempo indefinido, debe especificar una fecha de fin válida y posterior al inicio.');
        }

        if ($por_tiempo_indefinido) {
            $fecha_fin = null;
        }

        // Validar que el lugar esté seteado correctamente (exactamente uno).
        $lugares_indicados = array_filter(
            [$id_oficina, $id_almacen, $id_labor],
            fn ($v) => $v !== null,
        );
        if (count($lugares_indicados) !== 1) {
            return ApiResponse::error('Debe indicar exactamente un lugar de trabajo (almacén, labor u oficina).');
        }

        // Validar elegibilidad de cada empleado en una sola consulta.
        $elegibles = ProgramacionHorarioData::get_empleados_con_contrato_vigente(
            array_map('intval', $empleados)
        );

        $mapa_por_id = [];
        foreach ($elegibles as $row) {
            $mapa_por_id[(int) $row['id_empleado']] = $row;
        }

        $registros = [];
        $rechazados = [];

        foreach ($empleados as $id_empleado) {
            $id_empleado = (int) $id_empleado;

            if (! isset($mapa_por_id[$id_empleado])) {
                $emp = DB::table('empleado')->where('id', $id_empleado)->first();
                $nombre_completo = $emp ? trim($emp->nombre.' '.$emp->apellido) : "Empleado ID {$id_empleado}";
                $rechazados[] = [
                    'id_empleado' => $id_empleado,
                    'nombre' => $nombre_completo,
                    'motivo' => "{$nombre_completo}: El empleado no tiene un contrato Vigente.",
                ];

                continue;
            }

            $contrato = $mapa_por_id[$id_empleado];
            $nombre_completo = trim($contrato['nombre'].' '.$contrato['apellido']);
            $id_contrato = (int) $contrato['id_contrato_vigente'];
            $contrato_indefinido = (bool) $contrato['contrato_indefinido'];
            $contrato_fecha_fin = $contrato['contrato_fecha_fin'] ?? null;

            // Validar que la programación esté dentro de la vigencia del contrato
            if (! $contrato_indefinido && $contrato_fecha_fin !== null) {
                // Caso 1: Se intenta programar por tiempo indefinido, pero el contrato tiene fecha de fin
                if ($por_tiempo_indefinido) {
                    $rechazados[] = [
                        'id_empleado' => $id_empleado,
                        'nombre' => $nombre_completo,
                        'motivo' => "{$nombre_completo}: No se puede asignar una programación indefinida porque su contrato culmina el {$contrato_fecha_fin}.",
                    ];
                    continue;
                }
                // Caso 2: La fecha de inicio de la programación es posterior al fin del contrato
                if ($fecha_inicio > $contrato_fecha_fin) {
                    $rechazados[] = [
                        'id_empleado' => $id_empleado,
                        'nombre' => $nombre_completo,
                        'motivo' => "{$nombre_completo}: La fecha de inicio de la programación ({$fecha_inicio}) es posterior al término de su contrato ({$contrato_fecha_fin}).",
                    ];
                    continue;
                }
                // Caso 3: La fecha de fin de la programación es posterior al fin del contrato
                if ($fecha_fin !== null && $fecha_fin > $contrato_fecha_fin) {
                    $rechazados[] = [
                        'id_empleado' => $id_empleado,
                        'nombre' => $nombre_completo,
                        'motivo' => "{$nombre_completo}: Su contrato culmina el {$contrato_fecha_fin}, antes de la fecha de fin de la programación ({$fecha_fin}).",
                    ];
                    continue;
                }
            }

            // Evitar duplicado exacto Activo.
            if (ProgramacionHorarioData::existe_programacion_activa(
                id_empleado: $id_empleado,
                id_contrato_trabajo: $id_contrato,
                id_turno_laboral: $id_turno_laboral,
                fecha_inicio: $fecha_inicio,
            )) {
                $rechazados[] = [
                    'id_empleado' => $id_empleado,
                    'nombre' => $nombre_completo,
                    'motivo' => "{$nombre_completo}: Ya existe una programación Activa idéntica.",
                ];

                continue;
            }

            // Validar cruce de horarios con programaciones existentes del empleado.
            $conflicto = ProgramacionHorarioData::existe_cruce_horario(
                id_empleado: $id_empleado,
                id_turno_laboral: $id_turno_laboral,
                dias_laborables_nuevo: $dias_laborables,
                fecha_inicio_nuevo: $fecha_inicio,
                fecha_fin_nuevo: $fecha_fin,
            );
            if ($conflicto !== null) {
                $motivo_conflicto = "{$nombre_completo}: se cruza con una programación existente "
                    ."(#{$conflicto->id}, {$conflicto->hora_ingreso}-{$conflicto->hora_salida}).";
                $rechazados[] = [
                    'id_empleado' => $id_empleado,
                    'nombre' => $nombre_completo,
                    'motivo' => $motivo_conflicto,
                ];

                continue;
            }

            $registros[] = [
                'id_empleado' => $id_empleado,
                'id_contrato_trabajo' => $id_contrato,
                'id_turno_laboral' => $id_turno_laboral,
                'id_oficina' => $id_oficina,
                'id_almacen' => $id_almacen,
                'id_labor' => $id_labor,
                'fecha_inicio' => $fecha_inicio,
                'por_tiempo_indefinido' => $por_tiempo_indefinido,
                'fecha_fin' => $fecha_fin,
                'dias_laborables' => $dias_laborables,
                'estado' => EstadoBase::Activo->value,
            ];
        }

        if (empty($registros)) {
            return ApiResponse::error(
                'Ningún empleado pudo ser programado.',
                ['rechazados' => $rechazados]
            );
        }

        $ids_creados = DB::transaction(function () use ($registros) {
            return ProgramacionHorarioData::crear_programaciones_masivo($registros);
        });

        $creados = [];
        foreach ($ids_creados as $idx => $id_programacion) {
            $creados[] = ProgramacionHorarioData::get_programaciones(id_programacion: $id_programacion);
        }

        return ApiResponse::success([
            'programaciones' => $creados,
            'rechazados' => $rechazados,
            'total_creados' => count($creados),
            'total_rechazados' => count($rechazados),
        ], 'Horario asignado correctamente');
    }

    /**
     * Cambiar estado (Activo/Inactivo) de una programación.
     */
    public static function cambiar_estado(int $id_programacion, string $estado): array
    {
        ProgramacionHorarioData::cambiar_estado($id_programacion, $estado);

        return ApiResponse::success(null, 'Estado de la programación actualizado');
    }
}
