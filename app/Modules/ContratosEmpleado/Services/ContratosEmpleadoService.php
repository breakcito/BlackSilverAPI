<?php

namespace App\Modules\ContratosEmpleado\Services;

use App\Modules\ContratosEmpleado\Data\ContratosEmpleadoData;
use App\Shared\Enums\_Generic\EstadoBase;
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
        ?EstadoBase $estado = null,
    ): array {
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
        if (! $por_tiempo_indefinido) {
            if ($duracion === null || $periodo_duracion === null) {
                return ApiResponse::error('Si el contrato no es por tiempo indefinido, debe especificar duración y periodo.');
            }

            $fecha_fin = ContratosEmpleadoData::calcular_fecha_fin(
                fecha_inicio: $fecha_inicio,
                duracion: (int) $duracion,
                periodo_duracion: $periodo_duracion
            );
        }

        // Validar duplicado: mismo empleado, mismo cargo, misma fecha_inicio, Activo
        if (ContratosEmpleadoData::existe_contrato_activo(
            id_empleado: $id_empleado,
            id_cargo: $id_cargo,
            fecha_inicio: $fecha_inicio,
        )) {
            return ApiResponse::error('Ya existe un contrato Activo para este empleado con el mismo cargo y fecha de inicio.');
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
        ];

        // INSERT + UPDATE en transacción
        return DB::transaction(function () use ($payload, $evidenciasJson) {
            $id_empleado_tx = (int) $payload['id_empleado'];
            $fecha_inicio_tx = (string) $payload['fecha_inicio'];
            $esIndefinido_tx = (bool) ($payload['por_tiempo_indefinido'] ?? false);

            // Si el empleado tiene un contrato vigente con fecha_fin y el nuevo
            // contrato comienza ANTES de esa fecha_fin, cerramos el contrato
            // anterior anticipadamente con fecha_fin_anticipada = fecha_inicio
            // del nuevo.
            $vigentesActuales = ContratosEmpleadoData::get_contratos(
                id_empleado: $id_empleado_tx
            );

            if (! $esIndefinido_tx && ! empty($vigentesActuales)) {
                foreach ($vigentesActuales as $vigente) {
                    if (
                        ($vigente->estado ?? null) === EstadoBase::Activo->value
                        && ! empty($vigente->fecha_fin)
                        && $fecha_inicio_tx < (string) $vigente->fecha_fin
                    ) {
                        ContratosEmpleadoData::finalizar_anticipado(
                            (int) $vigente->id_contrato,
                            $fecha_inicio_tx
                        );
                    }
                }
            }

            $id_contrato = ContratosEmpleadoData::crear_contrato($payload, $evidenciasJson);

            // Actualizar empleado con el contrato vigente
            ContratosEmpleadoData::update_id_contrato_vigente_empleado(
                $id_empleado_tx,
                $id_contrato
            );

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
        ContratosEmpleadoData::finalizar_anticipado($id_contrato, $fecha_fin_anticipada);

        return ApiResponse::success(null, 'Contrato finalizado anticipadamente');
    }
}
