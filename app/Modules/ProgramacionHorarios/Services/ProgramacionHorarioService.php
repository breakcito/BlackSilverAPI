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

            // Snapshots del contrato al momento de crear la programación.
            // Estas 3 columnas NO se actualizan después; son trazabilidad histórica.
            $contrato_tipo = $contrato['contrato_tipo'] ?? null;
            $contrato_sueldo_base = $contrato['contrato_sueldo_base'] ?? null;
            $contrato_sueldo_real = $contrato['contrato_sueldo_real'] ?? null;
            $contrato_sueldo_diario = $contrato['contrato_sueldo_diario'] ?? null;

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
                // Snapshots del contrato
                'tipo_contrato' => $contrato_tipo,
                'sueldo_base' => $contrato_sueldo_base,
                'sueldo_real' => $contrato_sueldo_real,
                'sueldo_diario' => $contrato_sueldo_diario,
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

    /**
     * Finalizar anticipadamente una programación acotando su fecha_fin.
     */
    public static function finalizar_programacion_individual(int $id_programacion, string $fecha_fin): array
    {
        $prog = \App\Models\ProgramacionHorario::find($id_programacion);
        if (!$prog) {
            return ApiResponse::error('La programación no existe.');
        }

        if ($fecha_fin < $prog->fecha_inicio->toDateString()) {
            return ApiResponse::error('La fecha de finalización no puede ser anterior a la fecha de inicio.');
        }

        $prog->fecha_fin = $fecha_fin;
        $prog->por_tiempo_indefinido = false;
        $prog->save();

        $actualizado = ProgramacionHorarioData::get_programaciones(id_programacion: $id_programacion);

        return ApiResponse::success($actualizado, 'Programación finalizada correctamente');
    }

    /**
     * Cerrar (Inactivar) todas las programaciones Activas vinculadas a un contrato.
     *
     * Se usa cuando:
     *   - El contrato se finaliza anticipadamente.
     *   - El contrato vence (cron diario).
     *   - Se registra una adenda que cambia tipo_contrato, sueldo_base o sueldo_diario
     *     (los snapshots quedan desfasados, hay que reasignar).
     *
     * Si se pasa $fecha_fin, se setea como fecha_fin de las programaciones
     * afectadas (para auditoría). Si no, se usa hoy.
     *
     * @return array{ids: int[], total: int}
     */
    public static function finalizar_programaciones_por_contrato(
        int $id_contrato,
        ?string $fecha_fin = null,
    ): array {
        $fecha_fin = $fecha_fin ?? \Carbon\Carbon::now()->toDateString();

        $ids_activas = ProgramacionHorarioData::get_ids_programaciones_activas_por_contrato($id_contrato);
        if (empty($ids_activas)) {
            return ['ids' => [], 'total' => 0];
        }

        $affected = ProgramacionHorarioData::finalizar_programaciones_masivo($ids_activas, $fecha_fin);

        return [
            'ids' => array_map('intval', $ids_activas),
            'total' => (int) $affected,
        ];
    }

    /**
     * Ajusta automáticamente las programaciones de horario activas asociadas a un contrato
     * cuando se registra una adenda que modifica:
     *  - Snapshot salarial: tipo_contrato, sueldo_base, salario_diario
     *  - Lugar de trabajo: id_almacen, id_labor, id_oficina
     *  - Vigencia del contrato: fechas que acotan el rango de la programación
     *
     * 1. Preserva el historial del tramo pasado recortando su fecha_fin al día anterior
     *    a la fecha efectiva de la adenda ($fecha_efectiva - 1 día).
     * 2. Genera automáticamente una nueva programación a partir de $fecha_efectiva con el
     *    mismo turno y días laborables, cargando los NUEVOS snapshots y, si aplica, el
     *    nuevo lugar de trabajo.
     * 3. Si la programación cae completamente dentro del nuevo periodo (Case 1), se
     *    actualiza directamente con los nuevos valores.
     *
     * Coherencia de fechas:
     *  - new_program.fecha_fin = MIN(original_program.fecha_fin, contrato.fecha_fin)
     *    si el contrato tiene fecha_fin y la programación original la supera.
     *  - Si el contrato es indefinido, se respeta la programación original.
     *
     * @return array{actualizadas: int, divididas: int, creadas: int}
     */
    public static function actualizar_programaciones_por_adenda(
        int $id_contrato,
        string $fecha_efectiva,
        string $tipo_contrato,
        ?float $sueldo_base,
        ?float $salario_diario,
        ?int $id_almacen = null,
        ?int $id_labor = null,
        ?int $id_oficina = null,
        ?string $contrato_fecha_fin = null,
        bool $contrato_indefinido = false,
    ): array {
        $programaciones = \App\Models\ProgramacionHorario::query()
            ->where('id_contrato_trabajo', $id_contrato)
            ->where('estado', EstadoBase::Activo->value)
            ->get();

        if ($programaciones->isEmpty()) {
            return ['actualizadas' => 0, 'divididas' => 0, 'creadas' => 0];
        }

        $dia_anterior = \Carbon\Carbon::parse($fecha_efectiva)->subDay()->toDateString();
        $actualizadas = 0;
        $divididas = 0;
        $creadas = 0;

        // Helper inline: calcula la fecha_fin coherente con el contrato resultante.
        $calcularFechaFinCoherente = static function (?string $program_fecha_fin) use ($contrato_fecha_fin, $contrato_indefinido): ?string {
            // Contrato indefinido → respetar programación original.
            if ($contrato_indefinido) {
                return $program_fecha_fin;
            }
            // Sin fecha_fin en contrato → mantener.
            if ($contrato_fecha_fin === null) {
                return $program_fecha_fin;
            }
            // Programa indefinido + contrato finito → recortar al contrato.
            if ($program_fecha_fin === null) {
                return $contrato_fecha_fin;
            }
            // Ambos finitos: MIN.
            return $contrato_fecha_fin < $program_fecha_fin ? $contrato_fecha_fin : $program_fecha_fin;
        };

        foreach ($programaciones as $ph) {
            $f_inicio = $ph->fecha_inicio ? $ph->fecha_inicio->toDateString() : '';
            $f_fin = $ph->fecha_fin ? $ph->fecha_fin->toDateString() : null;

            $nueva_fecha_fin = $calcularFechaFinCoherente($f_fin);
            $nuevo_indefinido = $contrato_indefinido && $nueva_fecha_fin === null;

            // Caso 1: La programación inicia en o después de la fecha de la adenda.
            // Se actualizan snapshots, lugar y se acota fecha_fin al contrato.
            // Importante: el lugar de la programación debe quedar EXACTAMENTE como el
            // del contrato (un solo lugar), no una mezcla. Si el contrato cambió de
            // tipo de lugar (almacén → labor), sobrescribimos los tres campos.
            if ($f_inicio >= $fecha_efectiva) {
                $ph->tipo_contrato = $tipo_contrato;
                $ph->sueldo_base = $sueldo_base;
                $ph->sueldo_diario = $salario_diario;
                $ph->id_almacen = $id_almacen;
                $ph->id_labor = $id_labor;
                $ph->id_oficina = $id_oficina;
                $ph->fecha_fin = $nueva_fecha_fin;
                $ph->por_tiempo_indefinido = $nuevo_indefinido;
                $ph->save();
                $actualizadas++;
                continue;
            }

            // Caso 2: La programación inició antes de la adenda y se extiende hasta
            // (o más allá de) la fecha efectiva. Se divide en OLD + NEW.
            $solapa = $ph->por_tiempo_indefinido || ($f_fin !== null && $f_fin >= $fecha_efectiva);
            if ($solapa) {
                // OLD: recortar al día previo a la adenda, conservando lugar histórico.
                $ph->fecha_fin = $dia_anterior;
                $ph->por_tiempo_indefinido = false;
                $ph->save();
                $divididas++;

                // NEW: nuevo tramo desde fecha_efectiva con los snapshots actualizados
                // y EXACTAMENTE el lugar del contrato (sin fallback a la OLD). Esto
                // evita que la nueva programación herede un lugar de tipo distinto.
                \App\Models\ProgramacionHorario::create([
                    'id_empleado' => $ph->id_empleado,
                    'id_contrato_trabajo' => $ph->id_contrato_trabajo,
                    'id_turno_laboral' => $ph->id_turno_laboral,
                    'id_oficina' => $id_oficina,
                    'id_almacen' => $id_almacen,
                    'id_labor' => $id_labor,
                    'fecha_inicio' => $fecha_efectiva,
                    'por_tiempo_indefinido' => $nuevo_indefinido,
                    'fecha_fin' => $nueva_fecha_fin,
                    'dias_laborables' => $ph->dias_laborables,
                    'estado' => EstadoBase::Activo->value,
                    'tipo_contrato' => $tipo_contrato,
                    'sueldo_base' => $sueldo_base,
                    'sueldo_diario' => $salario_diario,
                ]);
                $creadas++;
            }
            // Si la programación es enteramente anterior a fecha_efectiva (no solapa),
            // se deja intacta: historial pasado.
        }

        return [
            'actualizadas' => $actualizadas,
            'divididas' => $divididas,
            'creadas' => $creadas,
        ];
    }
}
