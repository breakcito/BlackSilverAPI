<?php

namespace App\Modules\ContratosEmpleado\Services;

use App\Modules\ContratosEmpleado\Data\ContratosEmpleadoData;
use App\Modules\ProgramacionHorarios\Services\ProgramacionHorarioService;
use App\Shared\Enums\Contrato\EstadoContrato;
use App\Shared\Enums\Contrato\TipoContrato;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ContratosEmpleadoService
{
    /**
     * Listar contratos con filtros.
     */
    public static function get_contratos(
        ?int $id_empleado = null,
        ?EstadoContrato $estado = null,
    ) {
        $data = ContratosEmpleadoData::get_contratos(id_empleado: $id_empleado, estado: $estado);

        return ApiResponse::success($data);
    }

    /**
     * Ver un contrato por id.
     */
    public static function get_contrato_by_id(int $id_contrato): array
    {
        $data = ContratosEmpleadoData::get_contratos(id_contrato: $id_contrato);

        return ApiResponse::success($data);
    }

    /**
     * Historial de contratos de un empleado.
     */
    public static function get_historial_por_empleado(int $id_empleado): array
    {
        $data = ContratosEmpleadoData::get_historial_por_empleado($id_empleado);

        return ApiResponse::success($data, 'Historial obtenido correctamente');
    }

    /**
     * Registrar un contrato de trabajo con posibles evidencias (array de archivos).
     * Marca al empleado con `id_contrato_vigente = nuevo id`.
     *
     * @param  UploadedFile[]  $evidencias  Archivos subidos
     */
    public static function crear_contrato(
        int $id_empleado,
        int $id_cargo,
        ?int $id_empresa = null,
        ?int $id_almacen = null,
        ?int $id_labor = null,
        ?int $id_oficina = null,
        string $tipo_contrato = 'Planilla',
        ?float $sueldo_base = null,
        ?float $sueldo_real = null,
        ?float $salario_diario = null,
        string $fecha_inicio = '',
        bool $por_tiempo_indefinido = false,
        ?int $duracion = null,
        ?string $periodo_duracion = null,
        ?array $evidencias = [],
    ): array {
        // Defensa en profundidad: el Controller ya exige min:1, pero el Service
        // también lo valida para evitar contratos huérfanos con id_empleado = 0.
        if ($id_empleado < 1) {
            return ApiResponse::error('Debe especificar un empleado válido (id_empleado >= 1).');
        }

        // Validar tipo
        $tiposValidos = ['Planilla', 'JornadaDiaria', 'PeriodoPrueba'];
        if (! in_array($tipo_contrato, $tiposValidos, true)) {
            return ApiResponse::error('Tipo de contrato inválido.');
        }

        // Validar exclusividad sueldo_base vs salario_diario
        if (in_array($tipo_contrato, ['Planilla', 'PeriodoPrueba'], true) && $salario_diario !== null) {
            return ApiResponse::error('Para Planilla y PeriodoPrueba, salario_diario debe ser NULL.');
        }
        if ($tipo_contrato === 'JornadaDiaria' && $sueldo_base !== null) {
            return ApiResponse::error('Para JornadaDiaria, sueldo_base debe ser NULL.');
        }

        // Validar exclusividad de lugar: EXACTAMENTE uno de los tres (almacén, labor u oficina).
        $lugaresIndicados = array_filter(
            [$id_almacen, $id_labor, $id_oficina],
            fn ($v) => $v !== null && $v > 0
        );
        if (count($lugaresIndicados) === 0) {
            return ApiResponse::error('Debe indicar exactamente un lugar de trabajo (almacén, labor u oficina).');
        }
        if (count($lugaresIndicados) > 1) {
            return ApiResponse::error('Solo puede indicar un lugar de trabajo (almacén, labor u oficina), no varios.');
        }

        // Validar duracion cuando NO es indefinido
        $fecha_fin = null;
        $duracion_dias = null;
        if (! $por_tiempo_indefinido) {
            if ($duracion === null || $periodo_duracion === null) {
                return ApiResponse::error('Si el contrato no es por tiempo indefinido, debe especificar duración y periodo.');
            }

            $fecha_fin = ContratosEmpleadoData::calcular_fecha_fin(
                fecha_inicio: $fecha_inicio,
                duracion: (int) $duracion,
                periodo_duracion: $periodo_duracion
            );

            $duracion_dias = (int) abs(\Carbon\Carbon::parse($fecha_inicio)->diffInDays(\Carbon\Carbon::parse($fecha_fin)));
        }

        // Validar que el empleado no tenga ya un contrato Vigente.
        // Solo puede existir un contrato Vigente por empleado a la vez. Para
        // registrar uno nuevo primero hay que finalizar el vigente actual.
        $vigentes = ContratosEmpleadoData::get_contratos(id_empleado: $id_empleado, estado: EstadoContrato::Vigente);
        if (! empty($vigentes)) {
            return ApiResponse::error('El empleado ya tiene un contrato Vigente. Debe finalizarlo antes de registrar uno nuevo.');
        }

        // Validar duplicado: mismo empleado, mismo cargo, misma fecha_inicio, Vigente
        if (ContratosEmpleadoData::existe_contrato_activo(
            id_empleado: $id_empleado,
            id_cargo: $id_cargo,
            fecha_inicio: $fecha_inicio,
        )) {
            return ApiResponse::error('Ya existe un contrato Vigente para este empleado con el mismo cargo y fecha de inicio.');
        }

        // Guardar archivos de evidencia y obtener JSON serializado
        $evidenciasJson = null;
        if (! empty($evidencias)) {
            $archivosGuardados = ArchivoHelper::guardarArchivos('evidencias-contratos', $evidencias);
            if (! empty($archivosGuardados)) {
                $evidenciasJson = json_encode($archivosGuardados, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        // Construir payload
        $payload = [
            'id_empleado' => $id_empleado,
            'id_cargo' => $id_cargo,
            'id_empresa' => $id_empresa,
            'id_almacen' => $id_almacen,
            'id_labor' => $id_labor,
            'id_oficina' => $id_oficina,
            'tipo_contrato' => $tipo_contrato,
            'sueldo_base' => in_array($tipo_contrato, ['Planilla', 'PeriodoPrueba'], true) ? $sueldo_base : null,
            'sueldo_real' => in_array($tipo_contrato, ['Planilla', 'PeriodoPrueba'], true)
                ? ($sueldo_real !== null ? $sueldo_real : $sueldo_base)
                : null,
            'salario_diario' => $tipo_contrato === 'JornadaDiaria' ? $salario_diario : null,
            'fecha_inicio' => $fecha_inicio,
            'por_tiempo_indefinido' => $por_tiempo_indefinido,
            'fecha_fin' => $fecha_fin,
            'duracion' => $por_tiempo_indefinido ? null : $duracion,
            'periodo_duracion' => $por_tiempo_indefinido ? null : $periodo_duracion,
            'duracion_dias' => $duracion_dias,
        ];

        // INSERT + UPDATE en transacción
        return DB::transaction(function () use ($payload, $evidenciasJson) {
            $id_empleado_tx = (int) $payload['id_empleado'];
            $fecha_inicio_tx = (string) $payload['fecha_inicio'];
            $esIndefinido_tx = (bool) ($payload['por_tiempo_indefinido'] ?? false);

            // Estado inicial según la fecha de inicio.
            $estado_inicial = $fecha_inicio_tx <= now()->toDateString()
                ? EstadoContrato::Vigente->value
                : EstadoContrato::Pendiente->value;
            $payload['estado'] = $estado_inicial;

            // NOTA: La validación arriba garantiza que NO existe un contrato
            // Vigente previo. No auto-cerramos nada aquí — el frontend debe
            // llamar explícitamente al endpoint "finalizar-anticipado" antes.

            $id_contrato = ContratosEmpleadoData::crear_contrato($payload, $evidenciasJson);

            // Solo actualizamos id_contrato_vigente si el nuevo entra en vigencia
            // inmediatamente (estado Vigente). Si es Pendiente, no debe pisar
            // el vigente anterior (que ya no existe, garantizado por la validación).
            if ($estado_inicial === EstadoContrato::Vigente->value) {
                ContratosEmpleadoData::update_id_contrato_vigente_empleado(
                    $id_empleado_tx,
                    $id_contrato
                );
            }

            $nuevo = ContratosEmpleadoData::get_contratos(id_contrato: $id_contrato);

            // Devolver también el empleado actualizado (con id_contrato_vigente
            // y el cargo del contrato) para que el frontend pueda actualizar su lista
            // sin recargar toda la página.
            $empleadoActualizado = \App\Modules\Empleados\Data\EmpleadosData::get_empleados(
                id_empleado: $id_empleado_tx
            );

            return ApiResponse::success([
                'contrato' => $nuevo,
                'empleado' => $empleadoActualizado,
            ], 'Contrato registrado correctamente');
        });
    }

    /**
     * Finalizar un contrato anticipadamente.
     */
    public static function finalizar_anticipado(int $id_contrato, string $fecha_fin_anticipada, ?string $motivo_cierre = null, array $archivosEvidencias = []): array
    {
        return DB::transaction(function () use ($id_contrato, $fecha_fin_anticipada, $motivo_cierre, $archivosEvidencias) {
            $contrato = DB::table('contrato_trabajo')->where('id', $id_contrato)->first();
            if (! $contrato) {
                return ApiResponse::error('Contrato no encontrado.');
            }

            // Procesar evidencias subidas al finalizar y agregarlas acumulativamente
            if (! empty($archivosEvidencias)) {
                $nuevasEvidencias = ArchivoHelper::guardarArchivos('evidencias-contratos', $archivosEvidencias);
                if (! empty($nuevasEvidencias)) {
                    $evidenciasExistentes = json_decode((string) ($contrato->evidencias ?? '[]'), true);
                    if (! is_array($evidenciasExistentes)) {
                        $evidenciasExistentes = [];
                    }
                    $evidenciasCombinadas = array_merge($evidenciasExistentes, $nuevasEvidencias);
                    DB::table('contrato_trabajo')->where('id', $id_contrato)->update([
                        'evidencias' => json_encode($evidenciasCombinadas, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ]);
                }
            }

            ContratosEmpleadoData::finalizar_anticipado($id_contrato, $fecha_fin_anticipada, $motivo_cierre);

            $id_empleado = (int) $contrato->id_empleado;

            DB::table('empleado')
                ->where('id', $id_empleado)
                ->where('id_contrato_vigente', $id_contrato)
                ->update([
                    'id_contrato_vigente' => null,
                ]);

            // CASCADA: cerrar programaciones de horario activas del contrato.
            // El contrato ya no está vigente, no deben seguir contando hacia asistencia/planilla.
            $programaciones_finalizadas = ProgramacionHorarioService::finalizar_programaciones_por_contrato(
                id_contrato: $id_contrato,
                fecha_fin: $fecha_fin_anticipada,
            );

            $empleadoActualizado = \App\Modules\Empleados\Data\EmpleadosData::get_empleados(
                id_empleado: $id_empleado
            );

            return ApiResponse::success([
                'empleado' => $empleadoActualizado,
                'programaciones_finalizadas' => $programaciones_finalizadas,
                'cambios_detectados' => [
                    'afecta_snapshot' => true,
                    'afecta_lugar' => false,
                ],
            ], 'Contrato finalizado anticipadamente');
        });
    }

    /**
     * Mantenido por compatibilidad: inactiva Vigentes cuya fecha_fin ya pasó.
     * Usado por el comando programado `contratos:procesar-vencimientos-pendientes`.
     *
     * @param  bool  $dry_run  Si true, no escribe: solo devuelve el conteo que se inactivaría.
     */
    public static function inactivar_vencidos_no_indefinidos(bool $dry_run = false): array
    {
        $ids = ContratosEmpleadoData::get_ids_contratos_vencidos_no_indefinidos();

        $conteo = count($ids);

        if ($dry_run) {
            return [
                'total_evaluados' => $conteo,
                'total_inactivados' => 0,
                'dry_run' => true,
                'ids' => $ids,
            ];
        }

        $afectados = ContratosEmpleadoData::inactivar_contratos($ids);

        return [
            'total_evaluados' => $conteo,
            'total_inactivados' => $afectados,
            'dry_run' => false,
        ];
    }

    /**
     * Procesa diariamente el ciclo de vida de los contratos:
     *  1. Finaliza los Vigentes cuya fecha_fin ya pasó.
     *  2. Limpia `empleado.id_contrato_vigente` para los empleados cuyo vigente
     *     quedó en Finalizado y ya no hay contrato posterior que los apunte.
     *  3. Activa los Pendientes cuya fecha_inicio ya llegó.
     *
     * Cada paso se ejecuta en orden; el segundo paso trabaja sobre los efectos
     * del primero.
     *
     * @return array<string, int>
     */
    public static function procesar_vencimientos_y_pendientes(?string $fecha_referencia = null, bool $dry_run = false): array
    {
        $fecha = $fecha_referencia ?? \Carbon\Carbon::now()->toDateString();

        $resultado = [
            'fecha_referencia' => $fecha,
            'finalizados' => 0,
            'empleados_limpiados' => 0,
            'pendientes_activados' => 0,
            'dry_run' => $dry_run,
        ];

        // Paso 1: finalizar Vigentes con fecha_fin vencida.
        $ids_vencidos = ContratosEmpleadoData::get_ids_contratos_vencidos_no_indefinidos($fecha);
        $resultado['finalizados'] = $dry_run
            ? count($ids_vencidos)
            : ContratosEmpleadoData::inactivar_contratos($ids_vencidos);

        // Paso 2: limpiar id_contrato_vigente de los empleados cuyo vigente
        // quedó Finalizado y la fecha_fin ya pasó.
        $ids_empleados_huerfanos = ContratosEmpleadoData::get_empleados_con_vigente_finalizado($fecha);
        $resultado['empleados_limpiados'] = $dry_run
            ? count($ids_empleados_huerfanos)
            : ContratosEmpleadoData::limpiar_id_contrato_vigente($ids_empleados_huerfanos);

        // Paso 3: activar Pendientes cuya fecha_inicio ya llegó.
        $ids_pendientes = ContratosEmpleadoData::get_ids_contratos_pendientes_para_activar($fecha);
        $resultado['pendientes_activados'] = $dry_run
            ? count($ids_pendientes)
            : ContratosEmpleadoData::activar_contratos_pendientes($ids_pendientes);

        return $resultado;
    }

    private static function resolverValorAmigable(string $campo_bd, $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return 'Ninguno';
        }

        switch ($campo_bd) {
            case 'id_cargo':
                return DB::table('cargo')->where('id', $valor)->value('nombre') ?? $valor;
            case 'id_empresa':
                return DB::table('empresa')->where('id', $valor)->value('razon_social') ?? $valor;
            case 'id_almacen':
                return DB::table('almacen')->where('id', $valor)->value('nombre') ?? $valor;
            case 'id_labor':
                return DB::table('labor')->where('id', $valor)->value('nombre') ?? $valor;
            case 'id_oficina':
                return DB::table('oficina')->where('id', $valor)->value('nombre') ?? $valor;
            case 'por_tiempo_indefinido':
                return $valor ? 'Sí' : 'No';
            default:
                return (string) $valor;
        }
    }

    public static function registrar_adenda(
        int $id_contrato,
        int $id_empleado_sistema,
        ?string $motivo = null,
        array $datos_nuevos = [],
        array $evidencias = []
    ): array {
        return DB::transaction(function () use ($id_contrato, $id_empleado_sistema, $motivo, $datos_nuevos, $evidencias) {
            $contrato = \App\Models\ContratoTrabajo::find($id_contrato);
            if (!$contrato) {
                return ApiResponse::error('Contrato no encontrado.');
            }

            // Campos susceptibles de cambio
            $camposComparar = [
                'id_cargo',
                'id_empresa',
                'id_almacen',
                'id_labor',
                'id_oficina',
                'tipo_contrato',
                'sueldo_base',
                'sueldo_real',
                'salario_diario',
                'fecha_inicio',
                'por_tiempo_indefinido',
                'duracion',
                'periodo_duracion',
            ];

            $mapaNombres = [
                'id_cargo' => 'Cargo',
                'id_empresa' => 'Empresa',
                'id_almacen' => 'Almacén',
                'id_labor' => 'Labor',
                'id_oficina' => 'Oficina',
                'tipo_contrato' => 'Tipo de Contrato',
                'sueldo_base' => 'Sueldo Base',
                'sueldo_real' => 'Sueldo Real',
                'salario_diario' => 'Salario Diario',
                'fecha_inicio' => 'Fecha de Inicio',
                'por_tiempo_indefinido' => 'Por Tiempo Indefinido',
                'duracion' => 'Duración',
                'periodo_duracion' => 'Periodo de Duración',
            ];

            $cambios = [];

            // Pre-procesamiento de datos nuevos
            if (isset($datos_nuevos['por_tiempo_indefinido'])) {
                $datos_nuevos['por_tiempo_indefinido'] = (bool)$datos_nuevos['por_tiempo_indefinido'];
            }

            // Calcular fecha_fin y duracion_dias si aplica
            $por_tiempo_indefinido_nuevo = isset($datos_nuevos['por_tiempo_indefinido'])
                ? (bool)$datos_nuevos['por_tiempo_indefinido']
                : (bool)$contrato->por_tiempo_indefinido;

            if (!$por_tiempo_indefinido_nuevo) {
                $fecha_inicio_nueva = isset($datos_nuevos['fecha_inicio']) ? $datos_nuevos['fecha_inicio'] : $contrato->fecha_inicio->toDateString();
                $duracion_nueva = isset($datos_nuevos['duracion']) ? (int)$datos_nuevos['duracion'] : (int)$contrato->duracion;
                $periodo_duracion_nuevo = isset($datos_nuevos['periodo_duracion']) ? $datos_nuevos['periodo_duracion'] : $contrato->periodo_duracion;

                if ($duracion_nueva > 0 && $periodo_duracion_nuevo) {
                    $fecha_fin_nueva = ContratosEmpleadoData::calcular_fecha_fin($fecha_inicio_nueva, $duracion_nueva, $periodo_duracion_nuevo);
                    $datos_nuevos['fecha_fin'] = $fecha_fin_nueva;

                    $inicio = \Carbon\Carbon::parse($fecha_inicio_nueva);
                    $fin = \Carbon\Carbon::parse($fecha_fin_nueva);
                    // diffInDays en Carbon 3 devuelve valor con signo.
                    $datos_nuevos['duracion_dias'] = (int) abs($inicio->diffInDays($fin));
                }
            } else {
                $datos_nuevos['fecha_fin'] = null;
                $datos_nuevos['duracion'] = null;
                $datos_nuevos['periodo_duracion'] = null;
                $datos_nuevos['duracion_dias'] = null;
            }

            // Adicionalmente comparar fecha_fin y duracion_dias
            $camposComparar[] = 'fecha_fin';
            $camposComparar[] = 'duracion_dias';
            $mapaNombres['fecha_fin'] = 'Fecha de Fin';
            $mapaNombres['duracion_dias'] = 'Duración en Días';

            foreach ($camposComparar as $campo) {
                if (!array_key_exists($campo, $datos_nuevos)) {
                    continue;
                }

                $valorAnterior = $contrato->{$campo};
                $valorNuevo = $datos_nuevos[$campo];

                // Normalización para la comparación
                $normAnterior = $valorAnterior;
                $normNuevo = $valorNuevo;

                if ($valorAnterior instanceof \Carbon\Carbon) {
                    $normAnterior = $valorAnterior->toDateString();
                }
                if ($normAnterior instanceof \DateTimeInterface) {
                    $normAnterior = $normAnterior->format('Y-m-d');
                }

                // Casteo preciso de tipos según el campo
                if (in_array($campo, ['sueldo_base', 'salario_diario'], true)) {
                    $normAnterior = ($normAnterior !== null && $normAnterior !== '') ? (float) $normAnterior : null;
                    $normNuevo = ($normNuevo !== null && $normNuevo !== '') ? (float) $normNuevo : null;
                } elseif (in_array($campo, ['duracion', 'duracion_dias'], true) || (str_starts_with($campo, 'id_') && $campo !== 'id_empleado')) {
                    $normAnterior = ($normAnterior !== null && $normAnterior !== '') ? (int) $normAnterior : null;
                    $normNuevo = ($normNuevo !== null && $normNuevo !== '') ? (int) $normNuevo : null;
                } elseif ($campo === 'por_tiempo_indefinido') {
                    $normAnterior = $normAnterior !== null ? (bool) $normAnterior : null;
                    $normNuevo = $normNuevo !== null ? (bool) $normNuevo : null;
                } elseif (is_string($normAnterior) || is_string($normNuevo)) {
                    $normAnterior = $normAnterior !== null ? trim((string) $normAnterior) : null;
                    $normNuevo = $normNuevo !== null ? trim((string) $normNuevo) : null;
                }

                if ($normAnterior !== $normNuevo) {
                    // Resolver nombres amigables para campos específicos
                    $valorAnteriorAmigable = self::resolverValorAmigable($campo, $normAnterior);
                    $valorNuevoAmigable = self::resolverValorAmigable($campo, $normNuevo);

                    $cambios[] = [
                        'campo_bd' => $campo,
                        'campo' => $mapaNombres[$campo] ?? $campo,
                        'valor_anterior' => $valorAnteriorAmigable,
                        'valor_nuevo' => $valorNuevoAmigable,
                    ];
                }
            }

            if (empty($cambios)) {
                return ApiResponse::error('No se detectaron cambios en el contrato.');
            }

            // =====================================================================
            // Detección de cambios para cascada con Programación de Horarios
            // =====================================================================
            // Se dispara cuando cambia cualquier campo que afecte al horario del
            // trabajador: snapshot salarial (sueldo/tipo), lugar de trabajo
            // (almacén/labor/oficina) o la vigencia del contrato (fechas).
            // El backend hace todo el trabajo automáticamente: split del tramo
            // previo, nuevo tramo continuo con los nuevos valores y clip de
            // fecha_fin cuando el contrato se reduce.
            // =====================================================================
            $campos_cambiados = array_column($cambios, 'campo_bd');

            $cambios_afectan_programacion = in_array('tipo_contrato', $campos_cambiados, true)
                || in_array('sueldo_base', $campos_cambiados, true)
                || in_array('salario_diario', $campos_cambiados, true)
                || in_array('id_almacen', $campos_cambiados, true)
                || in_array('id_labor', $campos_cambiados, true)
                || in_array('id_oficina', $campos_cambiados, true)
                || in_array('fecha_inicio', $campos_cambiados, true)
                || in_array('fecha_fin', $campos_cambiados, true)
                || in_array('por_tiempo_indefinido', $campos_cambiados, true);

            // Obtener el nombre del empleado que hace el cambio para guardarlo en la trazabilidad
            $nombre_empleado_sistema = DB::table('empleado')
                ->where('id', $id_empleado_sistema)
                ->select(DB::raw('CONCAT(nombre, " ", apellido) AS nombre_completo'))
                ->value('nombre_completo') ?? 'Sistema';

            // Construir la adenda log
            $nuevoLog = [
                'id_empleado' => $id_empleado_sistema,
                'nombre_empleado' => $nombre_empleado_sistema,
                'motivo' => $motivo,
                'update_at' => \Carbon\Carbon::now()->toIso8601String(),
                'cambios' => $cambios,
            ];

            $historialLog = $contrato->cambios_log ?? [];
            array_unshift($historialLog, $nuevoLog); // Colocar el cambio más reciente al inicio

            // Actualizar contrato con los campos nuevos
            foreach ($datos_nuevos as $key => $val) {
                $contrato->{$key} = $val;
            }
            $contrato->cambios_log = $historialLog;

            // Recalcular estado del contrato si fecha_inicio o por_tiempo_indefinido
            // cambiaron. La adenda puede mover el inicio al futuro (→ Pendiente)
            // o traerlo al presente (→ Vigente).
            //
            // Solo se recalcula si el estado actual es Pendiente o Vigente: nunca
            // reactivamos un contrato Finalizado o con Término Anticipado.
            //
            // No tocamos empleado.id_contrato_vigente aquí: la adenda es una
            // modificación, no un desvinculamiento. El sistema ya tiene la
            // maquinaria (procesar_vencimientos_y_pendientes) que activa el
            // contrato Pendiente cuando llega su fecha_inicio y actualiza
            // id_contrato_vigente en ese momento.
            if (in_array($contrato->estado, [EstadoContrato::Pendiente->value, EstadoContrato::Vigente->value], true)) {
                $fecha_inicio_efectiva = $contrato->fecha_inicio
                    ? $contrato->fecha_inicio->toDateString()
                    : \Carbon\Carbon::now()->toDateString();
                $estado_recalculado = $fecha_inicio_efectiva <= \Carbon\Carbon::now()->toDateString()
                    ? EstadoContrato::Vigente->value
                    : EstadoContrato::Pendiente->value;

                if ($contrato->estado !== $estado_recalculado) {
                    $contrato->estado = $estado_recalculado;
                }
            }

            // Guardar archivos de evidencia y obtener JSON serializado si se suben
            if (!empty($evidencias)) {
                $archivosGuardados = ArchivoHelper::guardarArchivos('evidencias-contratos', $evidencias);
                if (!empty($archivosGuardados)) {
                    $existentes = is_array($contrato->evidencias) ? $contrato->evidencias : json_decode($contrato->evidencias ?? '[]', true) ?? [];
                    $contrato->evidencias = array_merge($existentes, $archivosGuardados);
                }
            }

            $contrato->save();

            // =====================================================================
            // CASCADA hacia Programación de Horarios (transparente para el usuario)
            // =====================================================================
            $programaciones_ajustadas = ['actualizadas' => 0, 'divididas' => 0, 'creadas' => 0];

            if ($cambios_afectan_programacion) {
                $fecha_efectiva = isset($datos_nuevos['fecha_inicio'])
                    ? $datos_nuevos['fecha_inicio']
                    : ($contrato->fecha_inicio ? $contrato->fecha_inicio->toDateString() : \Carbon\Carbon::now()->toDateString());

                $programaciones_ajustadas = ProgramacionHorarioService::actualizar_programaciones_por_adenda(
                    id_contrato: $id_contrato,
                    fecha_efectiva: $fecha_efectiva,
                    tipo_contrato: (string) $contrato->tipo_contrato,
                    sueldo_base: $contrato->sueldo_base !== null ? (float) $contrato->sueldo_base : null,
                    salario_diario: $contrato->salario_diario !== null ? (float) $contrato->salario_diario : null,
                    id_almacen: $contrato->id_almacen !== null ? (int) $contrato->id_almacen : null,
                    id_labor: $contrato->id_labor !== null ? (int) $contrato->id_labor : null,
                    id_oficina: $contrato->id_oficina !== null ? (int) $contrato->id_oficina : null,
                    contrato_fecha_fin: $contrato->fecha_fin ? $contrato->fecha_fin->toDateString() : null,
                    contrato_indefinido: (bool) $contrato->por_tiempo_indefinido,
                );
            }

            // Si el contrato modificado es el vigente, actualizar el cargo del empleado
            $empleado = DB::table('empleado')->where('id', $contrato->id_empleado)->first();
            if ($empleado && (int)$empleado->id_contrato_vigente === $contrato->id) {
                if (isset($datos_nuevos['id_cargo'])) {
                    DB::table('empleado')
                        ->where('id', $contrato->id_empleado)
                        ->update(['id_cargo' => $datos_nuevos['id_cargo']]);
                }
            }

            // Retornar empleado actualizado para refrescar UI
            $empleadoActualizado = \App\Modules\Empleados\Data\EmpleadosData::get_empleados(
                id_empleado: $contrato->id_empleado
            );

            return ApiResponse::success([
                'contrato' => ContratosEmpleadoData::get_contratos(id_contrato: $contrato->id),
                'empleado' => $empleadoActualizado,
                'programaciones_ajustadas' => $programaciones_ajustadas,
            ], 'Adenda registrada correctamente.');
        });
    }
}
