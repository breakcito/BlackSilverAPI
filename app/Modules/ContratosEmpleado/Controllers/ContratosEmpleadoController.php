<?php

namespace App\Modules\ContratosEmpleado\Controllers;

use App\Modules\ContratosEmpleado\Services\ContratosEmpleadoService;
use App\Shared\Enums\Contrato\EstadoContrato;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContratosEmpleadoController
{
    /**
     * Listar contratos con filtros opcionales.
     */
    public function get_contratos(Request $request): JsonResponse
    {
        $id_empleado = $request->query('id_empleado') ? (int) $request->query('id_empleado') : null;
        $estado_val = $request->query('estado');
        $estado = $estado_val ? EstadoContrato::tryFrom($estado_val) : null;

        return response()->json(
            ContratosEmpleadoService::get_contratos(id_empleado: $id_empleado, estado: $estado)
        );
    }

    /**
     * Ver un contrato por id.
     */
    public function get_contrato_by_id(Request $request, int $id_contrato): JsonResponse
    {
        return response()->json(ContratosEmpleadoService::get_contrato_by_id($id_contrato));
    }

    /**
     * Historial completo de contratos de un empleado.
     */
    public function get_historial_por_empleado(Request $request, int $id_empleado): JsonResponse
    {
        return response()->json(ContratosEmpleadoService::get_historial_por_empleado($id_empleado));
    }

    /**
     * Registrar un nuevo contrato de forma standalone.
     * Las evidencias llegan como array de UploadedFile (input name: `evidencias[]`).
     */
    public function crear_contrato(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_empleado' => 'required|integer|min:1',
            'id_cargo' => 'required|integer',
            'id_empresa' => 'nullable|integer',
            'id_almacen' => 'nullable|integer',
            'id_labor' => 'nullable|integer',
            'id_oficina' => 'nullable|integer',
            'tipo_contrato' => 'required|in:Planilla,JornadaDiaria,PeriodoPrueba',
            'sueldo_base' => 'nullable|numeric',
            'salario_diario' => 'nullable|numeric',
            'fecha_inicio' => 'required|date',
            'por_tiempo_indefinido' => 'nullable|boolean',
            'duracion' => 'nullable|integer|min:1',
            'periodo_duracion' => 'nullable|in:diario,semanal,mensual,anual',
            'evidencias' => 'nullable|array',
            'evidencias.*' => 'file|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $tipo = (string) $request->input('tipo_contrato');

        $sueldo_base = in_array($tipo, ['Planilla', 'PeriodoPrueba'], true)
            ? ($request->input('sueldo_base') !== null ? (float) $request->input('sueldo_base') : null)
            : null;

        $salario_diario = $tipo === 'JornadaDiaria'
            ? ($request->input('salario_diario') !== null ? (float) $request->input('salario_diario') : null)
            : null;

        $indefinido = (bool) $request->boolean('por_tiempo_indefinido');

        $result = ContratosEmpleadoService::crear_contrato(
            id_empleado: (int) $request->input('id_empleado'),
            id_cargo: (int) $request->input('id_cargo'),
            id_empresa: $request->input('id_empresa') ? (int) $request->input('id_empresa') : null,
            id_almacen: $request->input('id_almacen') ? (int) $request->input('id_almacen') : null,
            id_labor: $request->input('id_labor') ? (int) $request->input('id_labor') : null,
            id_oficina: $request->input('id_oficina') ? (int) $request->input('id_oficina') : null,
            tipo_contrato: $tipo,
            sueldo_base: $sueldo_base,
            salario_diario: $salario_diario,
            fecha_inicio: (string) $request->input('fecha_inicio'),
            por_tiempo_indefinido: $indefinido,
            duracion: $request->input('duracion') ? (int) $request->input('duracion') : null,
            periodo_duracion: $request->input('periodo_duracion'),
            evidencias: $request->file('evidencias') ?? [],
        );

        return response()->json($result);
    }

    /**
     * Finalizar contrato anticipadamente.
     */
    public function finalizar_anticipado(Request $request, int $id_contrato): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fecha_fin_anticipada' => 'required|date',
            'motivo_cierre' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        return response()->json(ContratosEmpleadoService::finalizar_anticipado(
            $id_contrato,
            (string) $request->input('fecha_fin_anticipada'),
            $request->input('motivo_cierre') ? (string) $request->input('motivo_cierre') : null
        ));
    }

    /**
     * Registrar una adenda (modificación) a un contrato existente.
     */
    public function registrar_adenda(Request $request, int $id_contrato): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'motivo' => 'nullable|string',
            'id_cargo' => 'nullable|integer',
            'id_empresa' => 'nullable|integer',
            'id_almacen' => 'nullable|integer',
            'id_labor' => 'nullable|integer',
            'id_oficina' => 'nullable|integer',
            'tipo_contrato' => 'nullable|in:Planilla,JornadaDiaria,PeriodoPrueba',
            'sueldo_base' => 'nullable|numeric',
            'salario_diario' => 'nullable|numeric',
            'fecha_inicio' => 'nullable|date',
            'por_tiempo_indefinido' => 'nullable|boolean',
            'duracion' => 'nullable|integer|min:1',
            'periodo_duracion' => 'nullable|in:diario,semanal,mensual,anual',
            'evidencias' => 'nullable|array',
            'evidencias.*' => 'file|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $authUser = $request->attributes->get('auth_user');
        $id_empleado_sistema = $authUser ? (int) $authUser->id_empleado : 0;

        $datos = $request->only([
            'id_cargo',
            'id_empresa',
            'id_almacen',
            'id_labor',
            'id_oficina',
            'tipo_contrato',
            'sueldo_base',
            'salario_diario',
            'fecha_inicio',
            'por_tiempo_indefinido',
            'duracion',
            'periodo_duracion',
        ]);

        // Asegurar que si id_empresa, id_almacen, id_labor, id_oficina vienen como vacíos o ceros, los pasemos como null
        foreach (['id_empresa', 'id_almacen', 'id_labor', 'id_oficina'] as $id_field) {
            if (array_key_exists($id_field, $datos)) {
                $datos[$id_field] = ($datos[$id_field] && (int)$datos[$id_field] > 0) ? (int)$datos[$id_field] : null;
            }
        }

        // Si tipo_contrato cambia, limpiar el otro sueldo
        if (isset($datos['tipo_contrato'])) {
            if (in_array($datos['tipo_contrato'], ['Planilla', 'PeriodoPrueba'], true)) {
                $datos['salario_diario'] = null;
            } elseif ($datos['tipo_contrato'] === 'JornadaDiaria') {
                $datos['sueldo_base'] = null;
            }
        }

        $result = ContratosEmpleadoService::registrar_adenda(
            id_contrato: $id_contrato,
            id_empleado_sistema: $id_empleado_sistema,
            motivo: $request->input('motivo') ? (string) $request->input('motivo') : null,
            datos_nuevos: $datos,
            evidencias: $request->file('evidencias') ?? []
        );

        return response()->json($result);
    }
}
