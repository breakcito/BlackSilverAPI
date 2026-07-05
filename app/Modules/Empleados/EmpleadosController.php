<?php

namespace App\Modules\Empleados;

use App\Modules\ContratosEmpleado\Services\ContratosEmpleadoService;
use App\Services\EmpleadosService as EmpleadosServiceGlobal;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmpleadosController
{
    /**
     * Listar empleados
     */
    public function get_empleados(Request $request): JsonResponse
    {
        $result = EmpleadosService::get_empleados();

        return response()->json($result);
    }

    /**
     * Crear empleado
     */
    public function crear_empleado(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_cargo' => 'nullable|integer',
            'id_contrato_vigente' => 'nullable|integer',
            'con_contrato' => 'nullable|boolean',
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'genero' => 'nullable|string|max:16',
            'dni' => 'nullable|string|max:20',
            'ruc' => 'nullable|string|max:20',
            'carnet_extranjeria' => 'nullable|string|max:20',
            'pasaporte' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'nullable|date',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:128',
            'foto' => 'nullable|image|mimes:jpg,png,jpeg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $id_cargo_input = $request->input('id_cargo');
        $con_contrato = $request->boolean('con_contrato');

        // Si tiene contrato vigente, el id_cargo se gestiona con el contrato (no se requiere elegirlo en este flujo)
        if ($con_contrato) {
            $id_cargo = ! empty($id_cargo_input) ? (int) $id_cargo_input : 0;
        } else {
            if (empty($id_cargo_input)) {
                return response()->json(ApiResponse::error('Debe seleccionar un cargo.'));
            }
            $id_cargo = (int) $id_cargo_input;
        }

        $result = EmpleadosService::crear_empleado(
            id_cargo: $id_cargo,
            nombre: (string) $request->input('nombre'),
            apellido: (string) $request->input('apellido'),
            con_contrato: $con_contrato,
            id_contrato_vigente: $request->input('id_contrato_vigente') ? (int) $request->input('id_contrato_vigente') : null,
            genero: $request->input('genero'),
            dni: $request->input('dni'),
            ruc: $request->input('ruc'),
            carnet_extranjeria: $request->input('carnet_extranjeria'),
            pasaporte: $request->input('pasaporte'),
            fecha_nacimiento: $request->input('fecha_nacimiento'),
            direccion: $request->input('direccion'),
            telefono: $request->input('telefono'),
            email: $request->input('email'),
            foto: $request->file('foto')
        );

        return response()->json($result);
    }

    /**
     * Crear empleado con contrato (orquestador transaccional).
     * Primero crea el empleado, luego crea el contrato y actualiza `id_contrato_vigente`.
     */
    public function crear_empleado_con_contrato(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            // Datos del empleado
            'empleado.id_cargo' => 'nullable|integer',
            'empleado.nombre' => 'required|string|max:255',
            'empleado.apellido' => 'required|string|max:255',
            'empleado.con_contrato' => 'nullable|boolean',
            'empleado.genero' => 'nullable|string|max:16',
            'empleado.dni' => 'nullable|string|max:20',
            'empleado.ruc' => 'nullable|string|max:20',
            'empleado.carnet_extranjeria' => 'nullable|string|max:20',
            'empleado.pasaporte' => 'nullable|string|max:20',
            'empleado.fecha_nacimiento' => 'nullable|date',
            'empleado.direccion' => 'nullable|string|max:255',
            'empleado.telefono' => 'nullable|string|max:32',
            'empleado.email' => 'nullable|email|max:128',
            'empleado.foto' => 'nullable|image|mimes:jpg,png,jpeg|max:2048',

            // Datos del contrato
            'contrato.id_cargo' => 'required|integer',
            'contrato.id_empresa' => 'nullable|integer',
            'contrato.id_almacen' => 'nullable|integer',
            'contrato.id_labor' => 'nullable|integer',
            'contrato.tipo_contrato' => 'required|in:Planilla,JornadaDiaria',
            'contrato.sueldo_base' => 'nullable|numeric',
            'contrato.salario_diario' => 'nullable|numeric',
            'contrato.fecha_inicio' => 'required|date',
            'contrato.por_tiempo_indefinido' => 'nullable|boolean',
            'contrato.duracion' => 'nullable|integer|min:1',
            'contrato.periodo_duracion' => 'nullable|in:diario,semanal,mensual,anual',
            'contrato.evidencias' => 'nullable|array',
            'contrato.evidencias.*' => 'file|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $empData = $request->input('empleado');
        $ctrData = $request->input('contrato');

        // Si el empleado tiene contrato, el id_cargo se queda en 0 — el cargo vive en el contrato.
        $id_cargo_emp = isset($empData['id_cargo']) ? (int) $empData['id_cargo'] : 0;

        // Foto
        $foto = $request->file('empleado.foto');

        $result = DB::transaction(function () use ($empData, $id_cargo_emp, $foto, $ctrData, $request) {
            // 1. Crear empleado (sin id_contrato_vigente aún)
            $idEmpleado = (int) EmpleadosServiceGlobal::crear_empleado(
                id_cargo: $id_cargo_emp,
                nombre: (string) $empData['nombre'],
                apellido: (string) $empData['apellido'],
                con_contrato: true,
                id_contrato_vigente: null,
                genero: $empData['genero'] ?? null,
                dni: $empData['dni'] ?? null,
                ruc: $empData['ruc'] ?? null,
                carnet_extranjeria: $empData['carnet_extranjeria'] ?? null,
                pasaporte: $empData['pasaporte'] ?? null,
                fecha_nacimiento: $empData['fecha_nacimiento'] ?? null,
                direccion: $empData['direccion'] ?? null,
                telefono: $empData['telefono'] ?? null,
                email: $empData['email'] ?? null,
                foto: $foto
            )['data'];

            // 2. Crear contrato del empleado recién creado
            $evidencias = $request->file('contrato.evidencias') ?? [];

            $result = ContratosEmpleadoService::crear_contrato(
                id_empleado: $idEmpleado,
                id_cargo: (int) $ctrData['id_cargo'],
                id_empresa: isset($ctrData['id_empresa']) ? (int) $ctrData['id_empresa'] : null,
                id_almacen: isset($ctrData['id_almacen']) ? (int) $ctrData['id_almacen'] : null,
                id_labor: isset($ctrData['id_labor']) ? (int) $ctrData['id_labor'] : null,
                tipo_contrato: (string) $ctrData['tipo_contrato'],
                sueldo_base: isset($ctrData['sueldo_base']) ? (float) $ctrData['sueldo_base'] : null,
                salario_diario: isset($ctrData['salario_diario']) ? (float) $ctrData['salario_diario'] : null,
                fecha_inicio: (string) $ctrData['fecha_inicio'],
                por_tiempo_indefinido: (bool) ($ctrData['por_tiempo_indefinido'] ?? false),
                duracion: isset($ctrData['duracion']) ? (int) $ctrData['duracion'] : null,
                periodo_duracion: $ctrData['periodo_duracion'] ?? null,
                evidencias: $evidencias,
            );

            if (! $result['success']) {
                throw new \RuntimeException($result['message']);
            }

            $nuevoEmpleado = \App\Data\EmpleadosData::get_empleados(id_empleado: $idEmpleado);

            return ApiResponse::success([
                'empleado' => $nuevoEmpleado,
                'contrato' => $result['data'],
            ], 'Empleado y contrato registrados correctamente');
        });

        return response()->json($result);
    }

    /**
     * Actualizar foto de empleado
     */
    public function actualizar_foto(Request $request, int $id_empleado): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'foto' => 'required|image|mimes:jpg,png,jpeg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = EmpleadosServiceGlobal::actualizar_foto($id_empleado, $request->file('foto'));

        return response()->json($result);
    }
}
