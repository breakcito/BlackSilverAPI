<?php

namespace App\Modules\Asistencia\Services;

use App\Modules\Asistencia\Data\AsistenciaData;
use App\Modules\Asistencia\Data\MarcajeData;
use App\Shared\Enums\Asistencia\TipoMarcaje;
use App\Shared\Responses\ApiResponse;
use Carbon\Carbon;

/**
 * Capa de negocio del módulo Asistencia.
 *
 * Centraliza:
 *  - Cálculo de minutos de tardanza, total de horas (con cruce de medianoche) y jornada_trabajada.
 *  - Detección automática del siguiente tipo de marcaje (Ingreso/Salida) según el historial del día.
 *  - Cálculo de planilla en vivo: sueldo_base (Planilla) o salario_diario (JornadaDiaria) por jornada trabajada.
 */
class AsistenciaService
{
    /**
     * Lista las asistencias según filtros. Devuelve respuesta estandarizada.
     *
     * @param  array<string, mixed>  $filtros
     */
    public static function get_asistencias(array $filtros): array
    {
        $filas = AsistenciaData::get_asistencias_agrupadas($filtros);
        $dias_mes = isset($filtros['mes'], $filtros['year'])
            ? (int) date('t', mktime(0, 0, 0, (int) $filtros['mes'], 1, (int) $filtros['year']))
            : 30;

        foreach ($filas as &$fila) {
            $fila['pago_dia'] = self::calcular_pago_dia(
                (float) ($fila['jornada_trabajada'] ?? 0),
                $fila['tipo_contrato'] ?? null,
                $fila['sueldo_base'] !== null ? (float) $fila['sueldo_base'] : null,
                $fila['salario_diario'] !== null ? (float) $fila['salario_diario'] : null,
                $dias_mes,
            );
            $fila['marcajes'] = MarcajeData::get_marcajes_del_dia(
                (int) $fila['id_empleado'],
                (string) $fila['fecha'],
            );
        }

        return ApiResponse::success($filas);
    }

    /**
     * Cálculo de planilla en vivo: agrega por empleado los días trabajados y el pago total del mes.
     *
     * @param  array<string, mixed>  $filtros  mes, year, id_empleado?
     */
    public static function calcular_planilla(array $filtros): array
    {
        $filas = AsistenciaData::get_asistencias_agrupadas($filtros);

        $dias_mes = isset($filtros['mes'], $filtros['year'])
            ? (int) date('t', mktime(0, 0, 0, (int) $filtros['mes'], 1, (int) $filtros['year']))
            : 30;

        $por_empleado = [];
        foreach ($filas as $fila) {
            $id = (int) $fila['id_empleado'];
            if (! isset($por_empleado[$id])) {
                $por_empleado[$id] = [
                    'id_empleado' => $id,
                    'empleado' => trim(($fila['nombre'] ?? '').' '.($fila['apellido'] ?? '')),
                    'dni' => $fila['dni'] ?? null,
                    'url_foto' => $fila['url_foto'] ?? null,
                    'tipo_contrato' => $fila['tipo_contrato'] ?? null,
                    'sueldo_base' => $fila['sueldo_base'] !== null ? (float) $fila['sueldo_base'] : null,
                    'salario_diario' => $fila['salario_diario'] !== null ? (float) $fila['salario_diario'] : null,
                    'dias_trabajados' => 0,
                    'jornada_total' => 0.0,
                    'pago_total' => 0.0,
                ];
            }

            $jornada = (float) ($fila['jornada_trabajada'] ?? 0);
            $pago = self::calcular_pago_dia(
                $jornada,
                $fila['tipo_contrato'] ?? null,
                $fila['sueldo_base'] !== null ? (float) $fila['sueldo_base'] : null,
                $fila['salario_diario'] !== null ? (float) $fila['salario_diario'] : null,
                $dias_mes,
            );

            // Sumamos la jornada del día (puede ser < 1, = 1, o > 1).
            $por_empleado[$id]['jornada_total'] += $jornada;
            $por_empleado[$id]['pago_total'] += $pago;
            if ($jornada > 0) {
                $por_empleado[$id]['dias_trabajados'] += 1;
            }
        }

        // Redondeos finales.
        foreach ($por_empleado as &$row) {
            $row['jornada_total'] = round($row['jornada_total'], 4);
            $row['pago_total'] = round($row['pago_total'], 2);
        }

        return ApiResponse::success(array_values($por_empleado));
    }

    /**
     * Resuelve un QR token. NO crea el marcaje aún — solo valida el empleado
     * y devuelve los datos que el frontend necesita para continuar con el
     * paso de validación facial, junto con un `id_sesion` (UUID) que el
     * frontend conservará en estado local y usará para confirmar o cancelar
     * el proceso al final.
     *
     * El marcaje se crea al FINAL del proceso (en `confirmar_asistencia`
     * o en `cancelar_proceso`), nunca durante.
     *
     * @return array{success: bool, data?: array, message?: string}
     */
    public static function resolver_qr(string $qr_token, ?array $evidencia_inicial = null): array
    {
        // Búsqueda directa por qr_token.
        $sql = 'SELECT id, nombre, apellido, dni, url_foto, qr_token, estado, id_contrato_vigente
                FROM empleado WHERE qr_token = ? LIMIT 1';
        $row = \Illuminate\Support\Facades\DB::selectOne($sql, [$qr_token]);

        if (! $row) {
            return ApiResponse::error('Código QR no reconocido');
        }

        if (($row->estado ?? null) !== 'Activo') {
            return ApiResponse::error('Empleado inactivo. No puede registrar asistencia.');
        }

        $id_empleado = (int) $row->id;

        // Determinamos el siguiente tipo de marcaje según el historial del día.
        $ultimo = MarcajeData::get_ultimo_marcaje_hoy($id_empleado);
        $siguiente = self::detectar_siguiente_tipo($ultimo);

        // Buscamos la programación vigente hoy (si tiene).
        $programacion = self::get_programacion_vigente_hoy($id_empleado);

        // Generamos un id_sesion que el frontend conservará hasta confirmar/cancelar.
        $id_sesion = (string) \Illuminate\Support\Str::uuid();

        return ApiResponse::success([
            'id_sesion' => $id_sesion,
            'siguiente_tipo_marcaje' => $siguiente,
            'ultimo_marcaje_hoy' => $ultimo?->tipo_marcaje,
            'empleado' => [
                'id_empleado' => $id_empleado,
                'nombre' => $row->nombre,
                'apellido' => $row->apellido,
                'nombre_completo' => trim(($row->nombre ?? '').' '.($row->apellido ?? '')),
                'dni' => $row->dni,
                'url_foto' => $row->url_foto,
            ],
            'programacion_vigente' => $programacion,
            'evidencia_inicial' => $evidencia_inicial,
        ], 'QR detectado correctamente');
    }

    /**
     * Confirma el proceso de marcaje. Crea o actualiza la asistencia del día
     * según el siguiente tipo de marcaje (Ingreso o Salida).
     *
     * @param  array<string, mixed>  $evidencia_rostro
     * @return array{success: bool, data?: array, message?: string}
     */
    public static function confirmar_asistencia(
        int $id_marcaje = 0,
        ?array $evidencia_rostro = null,
        ?int $id_empleado_registro = null,
        ?string $id_sesion = null,
        ?int $id_empleado_param = null,
        ?array $evidencia_qr = null,
    ): array {
        // NOTA: ya no se busca el marcaje existente. El marcaje se CREA aquí,
        // al final del proceso exitoso. Si no se llegó a este punto, se crea
        // un marcaje incompleto desde `cancelar_proceso`.
        if ($id_empleado_param === null || $id_empleado_param < 1) {
            return ApiResponse::error('Empleado requerido (id_empleado).');
        }
        $id_empleado = $id_empleado_param;
        $fecha_hora_marcaje = Carbon::now();
        $fecha = $fecha_hora_marcaje->toDateString();

        // Determinamos el tipo: Ingreso si no hay marcaje previo hoy, Salida si ya hubo Ingreso.
        $ultimo_previo = MarcajeData::get_ultimo_marcaje_hoy($id_empleado);
        $tipo = self::detectar_siguiente_tipo($ultimo_previo);

        // Buscamos la programación para calcular tardanza / total_horas.
        $programacion = self::get_programacion_vigente_en_fecha($id_empleado, $fecha);
        $turno = $programacion['turno'] ?? null;

        // Calculamos tardanza (si es Ingreso) o total_horas (si es Salida).
        $minutos_tardanza = 0;
        $total_horas = null;
        $jornada_trabajada = 0.0;

        if ($tipo === TipoMarcaje::Ingreso) {
            if ($turno !== null && ! empty($turno['hora_ingreso'])) {
                $minutos_tardanza = self::calcular_minutos_tardanza(
                    $fecha_hora_marcaje,
                    $turno['hora_ingreso'],
                    (int) ($turno['minutos_tolerancia'] ?? 0),
                );
            }

            $id_asistencia = AsistenciaData::upsert_asistencia_diaria(
                $id_empleado,
                $fecha,
                [
                    'fecha_hora_ingreso' => $fecha_hora_marcaje,
                    'minutos_tardanza' => $minutos_tardanza,
                    'id_programacion_horario' => $programacion['id_programacion_horario'] ?? null,
                    'es_manual' => false,
                    'jornada_trabajada' => 0.0,
                ]
            );
        } else { // Salida
            // Recuperar asistencia del día para calcular total_horas.
            $asistencia_hoy = AsistenciaData::get_asistencia_del_dia($id_empleado, $fecha);
            if ($asistencia_hoy && $asistencia_hoy->fecha_hora_ingreso) {
                $total_horas = self::calcular_total_horas(
                    Carbon::parse($asistencia_hoy->fecha_hora_ingreso),
                    $fecha_hora_marcaje,
                );

                $turno_total_horas = isset($turno['total_horas']) && $turno['total_horas'] !== null
                    ? (float) $turno['total_horas']
                    : 8.0;

                $jornada_trabajada = $total_horas > 0
                    ? round($total_horas / $turno_total_horas, 4)
                    : 0.0;
            }

            $id_asistencia = AsistenciaData::upsert_asistencia_diaria(
                $id_empleado,
                $fecha,
                [
                    'fecha_hora_salida' => $fecha_hora_marcaje,
                    'total_horas' => $total_horas,
                    'id_programacion_horario' => $programacion['id_programacion_horario'] ?? null,
                    'es_manual' => false,
                    'jornada_trabajada' => $jornada_trabajada,
                ]
            );
        }

        // Construimos el array de evidencias.
        $evidencias = [];
        if ($evidencia_qr !== null) {
            $evidencias[] = array_merge($evidencia_qr, ['tipo' => 'qr']);
        }
        if ($evidencia_rostro !== null) {
            $evidencias[] = array_merge($evidencia_rostro, ['tipo' => 'rostro']);
        }
        $evidencias_json = ! empty($evidencias) ? json_encode($evidencias) : null;

        // CREAMOS el marcaje aquí, al final del proceso exitoso. El id_sesion
        // se persiste como referencia externa.
        $id_marcaje = MarcajeData::crear_marcaje([
            'id_empleado' => $id_empleado,
            'id_programacion_horario' => $programacion['id_programacion_horario'] ?? null,
            'id_asistencia' => $id_asistencia,
            'id_empleado_registro' => $id_empleado_registro,
            'fecha_hora' => $fecha_hora_marcaje,
            'tipo_marcaje' => $tipo->value,
            'proceso_confirmado' => true,
            'qr_leido' => true,
            'evidencias' => $evidencias_json,
        ]);

        return ApiResponse::success([
            'id_asistencia' => $id_asistencia,
            'id_marcaje' => $id_marcaje,
            'id_sesion' => $id_sesion,
            'tipo_marcaje' => $tipo->value,
            'minutos_tardanza' => $minutos_tardanza,
            'total_horas' => $total_horas,
            'jornada_trabajada' => $jornada_trabajada,
            'fecha' => $fecha,
        ], 'Asistencia registrada correctamente');
    }

    /**
     * Marca un proceso como cancelado / fallido. Solo actualiza el flag
     * `proceso_confirmado` (que ya es false por defecto al crear el marcaje).
     */
    /**
     * Crea un marcaje incompleto (proceso_confirmado=false) para registrar
     * el intento de marcaje cuando el usuario cancela, expira el timeout,
     * o cierra la pestaña.
     *
     * @return array{success: bool, data?: array, message?: string}
     */
    public static function cancelar_proceso(
        ?int $id_empleado = null,
        bool $llego_al_qr = true,
        ?int $id_programacion_horario = null,
        ?string $id_sesion = null,
        ?string $motivo = null,
        ?array $evidencia_qr = null,
    ): array {
        if ($id_empleado === null || $id_empleado < 1) {
            return ApiResponse::error('Empleado requerido (id_empleado).');
        }

        // Construimos el array de evidencias con la foto del QR y el motivo.
        $evidencias = [];
        if ($evidencia_qr !== null) {
            $evidencias[] = array_merge($evidencia_qr, ['tipo' => 'qr']);
        }
        if ($motivo !== null && $motivo !== '') {
            $evidencias[] = [
                'tipo' => 'cancelacion',
                'motivo' => $motivo,
                'id_sesion' => $id_sesion,
                'fecha_hora' => now()->toDateTimeString(),
            ];
        }
        $evidencias_json = ! empty($evidencias) ? json_encode($evidencias) : null;

        // CREAMOS el marcaje incompleto. Como no hay tipo_marcaje definido aún
        // (el usuario no llegó a confirmar), queda NULL.
        $id_marcaje = MarcajeData::crear_marcaje([
            'id_empleado' => $id_empleado,
            'id_programacion_horario' => $id_programacion_horario,
            'fecha_hora' => now(),
            'tipo_marcaje' => null,
            'proceso_confirmado' => false,
            'qr_leido' => $llego_al_qr,
            'evidencias' => $evidencias_json,
        ]);

        return ApiResponse::success([
            'id_marcaje' => $id_marcaje,
            'id_sesion' => $id_sesion,
        ], 'Proceso cancelado');
    }

    /**
     * Registra un marcaje manual desde el panel admin. Crea el marcaje
     * con es_manual=true y, si se ha completado un par (ingreso+salida),
     * crea o actualiza la asistencia del día.
     *
     * @param  array<string, mixed>  $payload  id_empleado, fecha_hora, tipo_marcaje, id_programacion_horario?, observaciones?
     */
    public static function registrar_marcaje_manual(array $payload, ?int $id_empleado_registro = null): array
    {
        $id_empleado = (int) ($payload['id_empleado'] ?? 0);
        $fecha_hora = Carbon::parse($payload['fecha_hora'] ?? now());
        $tipo_marcaje = TipoMarcaje::tryFrom((string) ($payload['tipo_marcaje'] ?? ''));
        $id_programacion = isset($payload['id_programacion_horario']) ? (int) $payload['id_programacion_horario'] : null;

        if ($tipo_marcaje === null) {
            return ApiResponse::error('Tipo de marcaje inválido. Use Ingreso o Salida.');
        }

        $id_marcaje = MarcajeData::crear_marcaje([
            'id_empleado' => $id_empleado,
            'id_programacion_horario' => $id_programacion,
            'id_empleado_registro' => $id_empleado_registro,
            'tipo_marcaje' => $tipo_marcaje->value,
            'fecha_hora' => $fecha_hora,
            'qr_leido' => false,
            'proceso_confirmado' => true,
            'es_manual' => true,
            'evidencias' => isset($payload['observaciones']) && $payload['observaciones'] !== ''
                ? json_encode([['tipo' => 'observacion', 'texto' => $payload['observaciones']]])
                : null,
        ]);

        // Si es Ingreso, crear/actualizar asistencia del día.
        $fecha = $fecha_hora->toDateString();
        $programacion = $id_programacion !== null
            ? self::get_programacion_by_id($id_programacion)
            : self::get_programacion_vigente_en_fecha($id_empleado, $fecha);

        if ($tipo_marcaje === TipoMarcaje::Ingreso) {
            $minutos_tardanza = 0;
            $turno = $programacion['turno'] ?? null;
            if ($turno !== null && ! empty($turno['hora_ingreso'])) {
                $minutos_tardanza = self::calcular_minutos_tardanza(
                    $fecha_hora,
                    $turno['hora_ingreso'],
                    (int) ($turno['minutos_tolerancia'] ?? 0),
                );
            }

            $id_asistencia = AsistenciaData::upsert_asistencia_diaria($id_empleado, $fecha, [
                'fecha_hora_ingreso' => $fecha_hora,
                'minutos_tardanza' => $minutos_tardanza,
                'id_programacion_horario' => $programacion['id_programacion_horario'] ?? null,
                'es_manual' => true,
                'jornada_trabajada' => 0.0,
            ]);

            MarcajeData::actualizar_marcaje($id_marcaje, ['id_asistencia' => $id_asistencia]);
        } else {
            // Salida: actualizar asistencia con salida + total + jornada.
            $asistencia_hoy = AsistenciaData::get_asistencia_del_dia($id_empleado, $fecha);
            $total_horas = null;
            $jornada_trabajada = 0.0;

            if ($asistencia_hoy && $asistencia_hoy->fecha_hora_ingreso) {
                $total_horas = self::calcular_total_horas(
                    Carbon::parse($asistencia_hoy->fecha_hora_ingreso),
                    $fecha_hora,
                );

                $turno = $programacion['turno'] ?? null;
                $turno_total_horas = isset($turno['total_horas']) && $turno['total_horas'] !== null
                    ? (float) $turno['total_horas']
                    : 8.0;

                $jornada_trabajada = $total_horas > 0
                    ? round($total_horas / $turno_total_horas, 4)
                    : 0.0;
            }

            $id_asistencia = AsistenciaData::upsert_asistencia_diaria($id_empleado, $fecha, [
                'fecha_hora_salida' => $fecha_hora,
                'total_horas' => $total_horas,
                'id_programacion_horario' => $programacion['id_programacion_horario'] ?? null,
                'es_manual' => true,
                'jornada_trabajada' => $jornada_trabajada,
            ]);

            MarcajeData::actualizar_marcaje($id_marcaje, ['id_asistencia' => $id_asistencia]);
        }

        return ApiResponse::success(['id_marcaje' => $id_marcaje], 'Marcaje manual registrado');
    }

    /**
     * Devuelve el detalle de una asistencia (incluye sus marcajes).
     */
    public static function get_asistencia_by_id(int $id_asistencia): array
    {
        $fila = \Illuminate\Support\Facades\DB::table('asistencia as a')
            ->where('a.id', $id_asistencia)
            ->first();

        if (! $fila) {
            return ApiResponse::error('Asistencia no encontrada');
        }

        $fila->marcajes = MarcajeData::get_marcajes_por_asistencia($id_asistencia);

        return ApiResponse::success($fila);
    }

    /**
     * Detecta si el siguiente marcaje del día debe ser Ingreso o Salida.
     *  - Si no hay marcajes previos → Ingreso.
     *  - Si el último fue Ingreso → Salida.
     *  - Si el último fue Salida → Ingreso (nuevo turno del mismo día).
     */
    private static function detectar_siguiente_tipo(?object $ultimo_marcaje_hoy): TipoMarcaje
    {
        if ($ultimo_marcaje_hoy === null) {
            return TipoMarcaje::Ingreso;
        }

        return ((string) $ultimo_marcaje_hoy->tipo_marcaje) === TipoMarcaje::Ingreso->value
            ? TipoMarcaje::Salida
            : TipoMarcaje::Ingreso;
    }

    /**
     * Calcula los minutos de tardanza comparando la hora real de marcaje
     * contra la hora teórica del turno más la tolerancia.
     *
     * Si la marcaje es ANTES de la hora teórica + tolerancia, retorna 0.
     */
    private static function calcular_minutos_tardanza(
        Carbon $fecha_hora_real,
        string $hora_ingreso_teorica,
        int $minutos_tolerancia,
    ): int {
        $hora_teorica = Carbon::parse($fecha_hora_real->toDateString().' '.$hora_ingreso_teorica);
        $limite = $hora_teorica->copy()->addMinutes($minutos_tolerancia);

        if ($fecha_hora_real->lessThanOrEqualTo($limite)) {
            return 0;
        }

        return abs((int) $fecha_hora_real->diffInMinutes($limite, false));
    }

    /**
     * Calcula el total de horas trabajadas entre dos marcaciones.
     * Soporta cruce de medianoche.
     */
    private static function calcular_total_horas(Carbon $ingreso, Carbon $salida): float
    {
        $diff_minutos = $ingreso->diffInMinutes($salida);
        $diff_horas = $diff_minutos / 60;

        return round($diff_horas, 2);
    }

    /**
     * Calcula el pago diario de una asistencia individual.
     *
     * Planilla: (sueldo_base / dias_mes) * jornada_trabajada
     * JornadaDiaria: salario_diario * jornada_trabajada
     */
    private static function calcular_pago_dia(
        float $jornada_trabajada,
        ?string $tipo_contrato,
        ?float $sueldo_base,
        ?float $salario_diario,
        int $dias_mes,
    ): float {
        if ($jornada_trabajada <= 0 || $dias_mes <= 0) {
            return 0.0;
        }

        return match ($tipo_contrato) {
            'Planilla' => $sueldo_base !== null
                ? round(($sueldo_base / $dias_mes) * $jornada_trabajada, 2)
                : 0.0,
            'JornadaDiaria' => $salario_diario !== null
                ? round($salario_diario * $jornada_trabajada, 2)
                : 0.0,
            default => 0.0,
        };
    }

    /**
     * Devuelve la programación vigente del empleado en la fecha de HOY,
     * junto con los datos del turno. Null si no tiene.
     *
     * @return array{id_programacion_horario?: int, turno?: array<string, mixed>}|null
     */
    private static function get_programacion_vigente_hoy(int $id_empleado): ?array
    {
        return self::get_programacion_vigente_en_fecha($id_empleado, now()->toDateString());
    }

    /**
     * @return array{id_programacion_horario?: int, turno?: array<string, mixed>}|null
     */
    private static function get_programacion_vigente_en_fecha(int $id_empleado, string $fecha): ?array
    {
        $sql = '
        SELECT
            ph.id AS id_programacion_horario,
            tl.id AS turno_id,
            tl.tipo_turno,
            tl.hora_ingreso,
            tl.hora_salida,
            tl.minutos_tolerancia,
            tl.total_horas,
            COALESCE(alm.nombre, lab.nombre) AS lugar_nombre,
            ph.dias_laborables
        FROM programacion_horario ph
        INNER JOIN turno_laboral tl ON tl.id = ph.id_turno_laboral
        LEFT JOIN almacen alm ON alm.id = ph.id_almacen
        LEFT JOIN labor lab ON lab.id = ph.id_labor
        WHERE ph.id_empleado = ?
          AND ph.estado = ?
          AND ph.fecha_inicio <= ?
          AND (ph.por_tiempo_indefinido = 1 OR ph.fecha_fin IS NULL OR ph.fecha_fin >= ?)
        ORDER BY ph.fecha_inicio DESC
        LIMIT 1
        ';

        $row = \Illuminate\Support\Facades\DB::selectOne($sql, [
            $id_empleado,
            'Activo',
            $fecha,
            $fecha,
        ]);

        if (! $row) {
            return null;
        }

        // Validamos que la fecha caiga en un día laborable (string "0101010").
        $dias_laborables = (string) $row->dias_laborables;
        if (strlen($dias_laborables) === 7) {
            $indice_dia = (int) Carbon::parse($fecha)->dayOfWeek; // 0=Dom, 1=Lun, ... 6=Sáb
            if ($dias_laborables[$indice_dia] === '0') {
                // No labora hoy. Devolvemos null pero conservamos la programación
                // para que el admin pueda ver que existe (aunque no autorice el flujo).
                return null;
            }
        }

        return [
            'id_programacion_horario' => (int) $row->id_programacion_horario,
            'lugar_nombre' => $row->lugar_nombre,
            'turno' => [
                'id' => (int) $row->turno_id,
                'tipo_turno' => $row->tipo_turno,
                'hora_ingreso' => $row->hora_ingreso,
                'hora_salida' => $row->hora_salida,
                'minutos_tolerancia' => (int) $row->minutos_tolerancia,
                'total_horas' => $row->total_horas !== null ? (float) $row->total_horas : null,
            ],
        ];
    }

    /**
     * Devuelve una programación específica por id (para marcajes manuales).
     *
     * @return array{id_programacion_horario?: int, turno?: array<string, mixed>}|null
     */
    private static function get_programacion_by_id(int $id_programacion): ?array
    {
        $sql = '
        SELECT
            ph.id AS id_programacion_horario,
            tl.id AS turno_id,
            tl.tipo_turno,
            tl.hora_ingreso,
            tl.hora_salida,
            tl.minutos_tolerancia,
            tl.total_horas
        FROM programacion_horario ph
        INNER JOIN turno_laboral tl ON tl.id = ph.id_turno_laboral
        WHERE ph.id = ?
        LIMIT 1
        ';

        $row = \Illuminate\Support\Facades\DB::selectOne($sql, [$id_programacion]);
        if (! $row) {
            return null;
        }

        return [
            'id_programacion_horario' => (int) $row->id_programacion_horario,
            'turno' => [
                'id' => (int) $row->turno_id,
                'tipo_turno' => $row->tipo_turno,
                'hora_ingreso' => $row->hora_ingreso,
                'hora_salida' => $row->hora_salida,
                'minutos_tolerancia' => (int) $row->minutos_tolerancia,
                'total_horas' => $row->total_horas !== null ? (float) $row->total_horas : null,
            ],
        ];
    }
}
