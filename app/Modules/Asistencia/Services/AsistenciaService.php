<?php

namespace App\Modules\Asistencia\Services;

use App\Modules\Asistencia\Data\AsistenciaData;
use App\Modules\Asistencia\Data\MarcajeData;
use App\Shared\Enums\Asistencia\TipoMarcaje;
use App\Shared\Responses\ApiResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Marcaje;

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
            $fila['id'] = 'success_' . $fila['id_asistencia'];
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
            $fila['estado'] = 'Exitoso';
        }

        // Obtener intentos fallidos identificados (donde id_empleado NO es nulo y proceso_confirmado = false)
        $queryFailed = Marcaje::query()
            ->select([
                'marcaje.id as id_marcaje_fallido',
                'marcaje.id_empleado',
                'marcaje.id_programacion_horario',
                'marcaje.fecha_hora',
                'marcaje.tipo_marcaje',
                'marcaje.evidencias',
                'marcaje.es_manual as marcaje_es_manual',
                'emp.nombre',
                'emp.apellido',
                'emp.dni',
                'emp.url_foto',
                'ct.tipo_contrato',
                'ct.sueldo_base',
                'ct.salario_diario',
                'car.nombre as cargo_nombre',
                'are.nombre as area_nombre',
                'tl.tipo_turno',
                'tl.hora_ingreso',
                'tl.hora_salida',
                'tl.minutos_tolerancia',
                'tl.total_horas as turno_total_horas',
                DB::raw("COALESCE(alm.nombre, lab.nombre) as lugar_nombre"),
                DB::raw("DATE(marcaje.fecha_hora) as fecha"),
            ])
            ->join('empleado as emp', 'emp.id', '=', 'marcaje.id_empleado')
            ->leftJoin('contrato_trabajo as ct', function ($join) {
                $join->on('ct.id_empleado', '=', 'marcaje.id_empleado')
                    ->whereRaw('DATE(marcaje.fecha_hora) >= ct.fecha_inicio')
                    ->whereRaw('(ct.fecha_fin IS NULL OR DATE(marcaje.fecha_hora) <= ct.fecha_fin)');
            })
            ->leftJoin('cargo as car', 'car.id', '=', 'ct.id_cargo')
            ->leftJoin('area as are', 'are.id', '=', 'car.id_area')
            ->leftJoin('programacion_horario as ph', 'ph.id', '=', 'marcaje.id_programacion_horario')
            ->leftJoin('turno_laboral as tl', 'tl.id', '=', 'ph.id_turno_laboral')
            ->leftJoin('almacen as alm', 'alm.id', '=', 'ph.id_almacen')
            ->leftJoin('labor as lab', 'lab.id', '=', 'ph.id_labor')
            ->where('marcaje.proceso_confirmado', false)
            ->whereNotNull('marcaje.id_empleado');

        if (!empty($filtros['mes'])) {
            $queryFailed->whereRaw('MONTH(marcaje.fecha_hora) = ?', [(int)$filtros['mes']]);
        }
        if (!empty($filtros['year'])) {
            $queryFailed->whereRaw('YEAR(marcaje.fecha_hora) = ?', [(int)$filtros['year']]);
        }
        if (!empty($filtros['id_empleado'])) {
            $queryFailed->where('marcaje.id_empleado', $filtros['id_empleado']);
        }
        if (!empty($filtros['q'])) {
            $queryFailed->where(function ($q) use ($filtros) {
                $q->where('emp.nombre', 'like', '%' . $filtros['q'] . '%')
                  ->orWhere('emp.apellido', 'like', '%' . $filtros['q'] . '%')
                  ->orWhere('emp.dni', 'like', '%' . $filtros['q'] . '%');
            });
        }

        $failedRows = $queryFailed->get()->toArray();
        $failedMapped = [];
        foreach ($failedRows as $fRow) {
            $failedMapped[] = [
                'id' => 'failed_' . $fRow['id_marcaje_fallido'],
                'id_asistencia' => null,
                'id_empleado' => $fRow['id_empleado'],
                'id_programacion_horario' => $fRow['id_programacion_horario'],
                'fecha_hora_ingreso' => $fRow['fecha_hora'],
                'fecha_hora_salida' => null,
                'total_horas' => 0.0,
                'jornada_trabajada' => 0.0,
                'minutos_tardanza' => 0,
                'asistencia_es_manual' => (bool)$fRow['marcaje_es_manual'],
                'nombre' => $fRow['nombre'],
                'apellido' => $fRow['apellido'],
                'dni' => $fRow['dni'],
                'url_foto' => $fRow['url_foto'],
                'tipo_contrato' => $fRow['tipo_contrato'],
                'sueldo_base' => $fRow['sueldo_base'] !== null ? (float)$fRow['sueldo_base'] : null,
                'salario_diario' => $fRow['salario_diario'] !== null ? (float)$fRow['salario_diario'] : null,
                'cargo_nombre' => $fRow['cargo_nombre'],
                'area_nombre' => $fRow['area_nombre'],
                'tipo_turno' => $fRow['tipo_turno'],
                'hora_ingreso' => $fRow['hora_ingreso'],
                'hora_salida' => $fRow['hora_salida'],
                'minutos_tolerancia' => $fRow['minutos_tolerancia'],
                'turno_total_horas' => $fRow['turno_total_horas'],
                'lugar_nombre' => $fRow['lugar_nombre'],
                'fecha' => $fRow['fecha'],
                'pago_dia' => 0.0,
                'estado' => 'Incompleto',
                'marcajes' => [
                    [
                        'id' => $fRow['id_marcaje_fallido'],
                        'id_empleado' => $fRow['id_empleado'],
                        'id_programacion_horario' => $fRow['id_programacion_horario'],
                        'fecha_hora' => $fRow['fecha_hora'],
                        'tipo_marcaje' => $fRow['tipo_marcaje'],
                        'proceso_confirmado' => false,
                        'evidencias' => $fRow['evidencias'],
                    ]
                ]
            ];
        }

        // Combinamos y ordenamos por fecha_hora_ingreso descendente
        $combined = array_merge($filas, $failedMapped);
        usort($combined, function ($a, $b) {
            return strcmp($b['fecha_hora_ingreso'] ?? '', $a['fecha_hora_ingreso'] ?? '');
        });

        return ApiResponse::success($combined);
    }

    /**
     * Obtiene los intentos fallidos anónimos (donde id_empleado es nulo y proceso_confirmado = false).
     */
    public static function get_intentos_fallidos_anonimos(array $filtros): array
    {
        $query = Marcaje::query()
            ->where('proceso_confirmado', false)
            ->whereNull('id_empleado');

        if (!empty($filtros['mes'])) {
            $query->whereRaw('MONTH(fecha_hora) = ?', [(int)$filtros['mes']]);
        }
        if (!empty($filtros['year'])) {
            $query->whereRaw('YEAR(fecha_hora) = ?', [(int)$filtros['year']]);
        }

        $rows = $query->orderBy('fecha_hora', 'desc')->get()->toArray();
        return ApiResponse::success($rows);
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

        if (empty($row->id_contrato_vigente)) {
            return ApiResponse::error('El empleado no tiene un contrato vigente activo. No puede registrar asistencia.');
        }

        $id_empleado = (int) $row->id;

        // Determinamos el siguiente tipo de marcaje según el historial del día.
        $ultimo = MarcajeData::get_ultimo_marcaje_hoy($id_empleado);
        $siguiente = self::detectar_siguiente_tipo($ultimo);

        // Buscamos la programación vigente hoy (si tiene).
        $programacion = self::get_programacion_vigente_hoy($id_empleado);

        // Si es un Ingreso y el turno programado de hoy ya pasó o es muy temprano, mostramos "Fuera de horario"
        if ($siguiente === TipoMarcaje::Ingreso->value && $programacion && !empty($programacion['turno'])) {
            $turno = $programacion['turno'];
            $hora_ingreso = $turno['hora_ingreso'] ?? null;
            $hora_salida = $turno['hora_salida'] ?? null;
            if ($hora_ingreso && $hora_salida) {
                $now = \Carbon\Carbon::now();
                $fecha_hoy = \Carbon\Carbon::today()->toDateString();
                
                $dt_ingreso = \Carbon\Carbon::parse($fecha_hoy . ' ' . $hora_ingreso);
                $dt_salida = \Carbon\Carbon::parse($fecha_hoy . ' ' . $hora_salida);
                
                // Si el turno cruza la medianoche
                if ($dt_salida->lessThan($dt_ingreso)) {
                    $dt_salida->addDay();
                }
                
                // Límite temprano: 30 minutos antes del ingreso
                $dt_limite_temprano = (clone $dt_ingreso)->subMinutes(30);
                
                if ($now->lessThan($dt_limite_temprano)) {
                    return ApiResponse::error('Aún no es hora de ingresar a su turno (Fuera de horario).');
                }
                if ($now->greaterThan($dt_salida)) {
                    return ApiResponse::error('Su turno programado para el día de hoy ya finalizó (Fuera de horario).');
                }
            }
        }

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

        // Recuperar asistencia del día existente.
        $asistencia_hoy = AsistenciaData::get_asistencia_del_dia($id_empleado, $fecha);

        // Calculamos tardanza (solo si es el primer ingreso del día).
        $minutos_tardanza = 0;
        if ($tipo === TipoMarcaje::Ingreso && $asistencia_hoy === null) {
            if ($turno !== null && ! empty($turno['hora_ingreso'])) {
                $minutos_tardanza = self::calcular_minutos_tardanza(
                    $fecha_hora_marcaje,
                    $turno['hora_ingreso'],
                    (int) ($turno['minutos_tolerancia'] ?? 0),
                );
            }
        } elseif ($asistencia_hoy !== null) {
            $minutos_tardanza = (int) $asistencia_hoy->minutos_tardanza;
        }

        // Simulamos el nuevo marcaje actual para calcular el consolidado del día.
        $nuevo_marcaje = [
            'tipo_marcaje' => $tipo->value,
            'fecha_hora' => $fecha_hora_marcaje->toDateTimeString(),
        ];

        $consolidado = self::consolidar_asistencia_diaria($id_empleado, $fecha, $nuevo_marcaje);

        $payload = [
            'id_programacion_horario' => $consolidado['id_programacion_horario'],
            'es_manual' => false,
            'total_horas' => $consolidado['total_horas'],
            'jornada_trabajada' => $consolidado['jornada_trabajada'],
            'minutos_tardanza' => $minutos_tardanza,
        ];

        if ($consolidado['fecha_hora_ingreso'] !== null) {
            $payload['fecha_hora_ingreso'] = $consolidado['fecha_hora_ingreso'];
        }
        if ($consolidado['fecha_hora_salida'] !== null) {
            $payload['fecha_hora_salida'] = $consolidado['fecha_hora_salida'];
        }

        $id_asistencia = AsistenciaData::upsert_asistencia_diaria(
            $id_empleado,
            $fecha,
            $payload,
            true // sobreescribir_jornada = true
        );

        $total_horas = $consolidado['total_horas'];
        $jornada_trabajada = $consolidado['jornada_trabajada'];

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

    /**
     * Consolida las horas y la jornada diaria basándose en todos los marcajes confirmados
     * del día para el empleado, más el nuevo marcaje actual simulado.
     *
     * @param  array<string, mixed>|null  $nuevo_marcaje
     * @return array{fecha_hora_ingreso: Carbon|null, fecha_hora_salida: Carbon|null, total_horas: float, jornada_trabajada: float, id_programacion_horario: int|null}
     */
    private static function consolidar_asistencia_diaria(int $id_empleado, string $fecha, ?array $nuevo_marcaje = null): array
    {
        // 1. Obtener marcajes previos ya confirmados del día
        $marcajes_previos = \Illuminate\Support\Facades\DB::table('marcaje')
            ->where('id_empleado', $id_empleado)
            ->whereDate('fecha_hora', $fecha)
            ->where('proceso_confirmado', 1)
            ->orderBy('fecha_hora')
            ->get()
            ->toArray();

        $marcajes = [];
        foreach ($marcajes_previos as $m) {
            $marcajes[] = (object) $m;
        }

        if ($nuevo_marcaje !== null) {
            $marcajes[] = (object) $nuevo_marcaje;
        }

        // 2. Ordenar por fecha_hora
        usort($marcajes, function ($a, $b) {
            return strcmp((string) $a->fecha_hora, (string) $b->fecha_hora);
        });

        // 3. Calcular total_horas por tramos
        $total_segundos = 0;
        /** @var Carbon|null $ultimo_ingreso */
        $ultimo_ingreso = null;
        /** @var Carbon|null $fecha_hora_ingreso */
        $fecha_hora_ingreso = null;
        /** @var Carbon|null $fecha_hora_salida */
        $fecha_hora_salida = null;

        foreach ($marcajes as $m) {
            $tipo_m = (string) ($m->tipo_marcaje ?? '');
            $fh = Carbon::parse((string) $m->fecha_hora);

            if ($tipo_m === 'Ingreso') {
                $ultimo_ingreso = $fh;
                if ($fecha_hora_ingreso === null) {
                    $fecha_hora_ingreso = $fh;
                }
            } elseif ($tipo_m === 'Salida') {
                $fecha_hora_salida = $fh;
                if ($ultimo_ingreso !== null) {
                    $total_segundos += abs($fh->diffInSeconds($ultimo_ingreso));
                    $ultimo_ingreso = null;
                }
            }
        }

        $total_horas = round($total_segundos / 3600.0, 4);

        // Obtener programación para el divisor
        $programacion = self::get_programacion_vigente_en_fecha($id_empleado, $fecha);
        $turno = $programacion['turno'] ?? null;
        $turno_total_horas = isset($turno['total_horas']) && $turno['total_horas'] !== null
            ? (float) $turno['total_horas']
            : 8.0;

        $jornada_trabajada = $total_horas > 0
            ? round($total_horas / $turno_total_horas, 4)
            : 0.0;

        return [
            'fecha_hora_ingreso' => $fecha_hora_ingreso,
            'fecha_hora_salida' => $fecha_hora_salida,
            'total_horas' => $total_horas,
            'jornada_trabajada' => $jornada_trabajada,
            'id_programacion_horario' => $programacion['id_programacion_horario'] ?? null,
        ];
    }
}
