<?php

namespace App\Controllers;

use App\Services\AlmacenService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class AlmacenController extends Controller
{
    public function __construct(
        private AlmacenService $almacenService
    ) {}

    public function get_almacenes(Request $request): JsonResponse
    {
        $result = $this->almacenService->get_almacenes();

        return response()->json($result);
    }

    public function crear_almacen(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:128',
            'descripcion' => 'nullable|string',
            'es_principal' => 'required|boolean',
        ], [
            'nombre.required' => 'El nombre es obligatorio',
            'es_principal.required' => 'Debe indicar si es almacén principal',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = $this->almacenService->crear_almacen(
            $request->nombre,
            $request->descripcion ?? null,
            $request->es_principal
        );

        return response()->json($result);
    }

    // --- SUBMÓDULOS DE ALMACÉN ---

    public function asignar_responsable_almacen(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_almacen' => 'required|integer',
            'id_usuario' => 'required|integer',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date|after:fecha_inicio',
        ], [
            'id_almacen.required' => 'El almacén es requerido',
            'id_usuario.required' => 'El usuario es requerido',
            'fecha_inicio.required' => 'La fecha de inicio es requerida',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = $this->almacenService->asignar_responsable_almacen(
            $request->id_almacen,
            $request->id_usuario,
            $request->fecha_inicio,
            $request->fecha_fin
        );

        return response()->json($result);
    }

    public function get_responsables_almacen(Request $request): JsonResponse
    {
        $id_almacen = $request->input('id_almacen');
        if (! $id_almacen) {
            return response()->json(ApiResponse::error('El id_almacen es requerido'), 400);
        }

        $result = $this->almacenService->get_responsables_almacen((int) $id_almacen);

        return response()->json($result);
    }

    public function asignar_mina_almacen(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_almacen' => 'required|integer',
            'id_mina' => 'required|integer',
        ], [
            'id_almacen.required' => 'El almacén es requerido',
            'id_mina.required' => 'La mina es requerida',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = $this->almacenService->asignar_mina_almacen(
            $request->id_almacen,
            $request->id_mina
        );

        return response()->json($result);
    }

    public function get_minas_almacen(Request $request): JsonResponse
    {
        $id_almacen = $request->input('id_almacen');
        if (! $id_almacen) {
            return response()->json(ApiResponse::error('El id_almacen es requerido'), 400);
        }

        $result = $this->almacenService->get_minas_almacen((int) $id_almacen);

        return response()->json($result);
    }

    public function desasignar_mina_almacen(Request $request): JsonResponse
    {
        $id_asignacion = $request->input('id_asignacion');
        if (! $id_asignacion) {
            return response()->json(ApiResponse::error('El id_asignacion es requerido'), 400);
        }

        $result = $this->almacenService->desasignar_mina_almacen((int) $id_asignacion);

        return response()->json($result);
    }
}
