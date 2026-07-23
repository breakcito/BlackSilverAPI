<?php

namespace App\Modules\ContratosEmpleado\Services;

use App\Modules\ContratosEmpleado\Data\ContratosEmpleadoData;
use App\Shared\Enums\Contrato\EstadoContrato;
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
        string $tipo_contrato = 'Planilla',
        ?float $sueldo_base = null,
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
        $tiposValidos = ['Planilla', 'JornadaDiaria'];
        if (! in_array($tipo_contrato, $tiposValidos, true)) {
            return ApiResponse::error('Tipo de contrato inválido.');
        }

        // Validar exclusividad sueldo_base vs salario_diario
        if ($tipo_contrato === 'Planilla' && $salario_diario !== null) {
            return ApiResponse::error('Para Planilla, salario_diario debe ser NULL.');
        }
        if ($tipo_contrato === 'JornadaDiaria' && $sueldo_base !== null) {
            return ApiResponse::error('Para JornadaDiaria, sueldo_base debe ser NULL.');
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

            $duracion_dias = (int) \Carbon\Carbon::parse($fecha_inicio)->diffInDays(\Carbon\Carbon::parse($fecha_fin));
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
            'id_oficina' => null,
            'tipo_contrato' => $tipo_contrato,
            'sueldo_base' => $tipo_contrato === 'Planilla' ? $sueldo_base : null,
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
    public static function finalizar_anticipado(int $id_contrato, string $fecha_fin_anticipada): array
    {
        return DB::transaction(function () use ($id_contrato, $fecha_fin_anticipada) {
            $contrato = DB::table('contrato_trabajo')->where('id', $id_contrato)->first();
            if (! $contrato) {
                return ApiResponse::error('Contrato no encontrado.');
            }

            ContratosEmpleadoData::finalizar_anticipado($id_contrato, $fecha_fin_anticipada);

            $id_empleado = (int) $contrato->id_empleado;

            DB::table('empleado')
                ->where('id', $id_empleado)
                ->where('id_contrato_vigente', $id_contrato)
                ->update([
                    'id_contrato_vigente' => null,
                ]);

            $empleadoActualizado = \App\Modules\Empleados\Data\EmpleadosData::get_empleados(
                id_empleado: $id_empleado
            );

            return ApiResponse::success([
                'empleado' => $empleadoActualizado,
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
}
