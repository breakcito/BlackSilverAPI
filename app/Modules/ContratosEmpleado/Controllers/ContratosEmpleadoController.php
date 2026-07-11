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
            'tipo_contrato' => 'required|in:Planilla,JornadaDiaria',
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

        $sueldo_base = $tipo === 'Planilla'
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
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        return response()->json(ContratosEmpleadoService::finalizar_anticipado(
            $id_contrato,
            (string) $request->input('fecha_fin_anticipada')
        ));
    }
}
