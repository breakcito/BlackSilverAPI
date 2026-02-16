<?php

namespace App\Modules\Empresas\Controllers;

use Illuminate\Http\Request;
use App\Modules\Empresas\Services\AlmacenService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class AlmacenController extends Controller
{
    public function __construct(
        private AlmacenService $almacenService
    ) {}

    public function get_almacenes(Request $request): JsonResponse
    {
        $id_empresa = $request->query('id_empresa');
        // El filtro es opcional ahora, si no viene, se listan todos
            
        $result = $this->almacenService->get_almacenes($id_empresa ? (int)$id_empresa : null);
        return response()->json($result);
    }

    public function crear_almacen(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_empresa'   => 'required|integer',
            'nombre'       => 'required|string|max:128',
            'descripcion'  => 'nullable|string',
            'es_principal' => 'required|boolean',
        ], [
            'id_empresa.required'   => 'La empresa no fue seleccionada',
            'nombre.required'       => 'El nombre es obligatorio',
            'es_principal.required' => 'Debe indicar si es almacén principal',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = $this->almacenService->crear_almacen(
            $request->id_empresa,
            $request->nombre,
            $request->descripcion ?? null,
            $request->es_principal
        );

        return response()->json($result);
    }

    // --- SUBMÓDULOS DE ALMACÉN ---

    public function asignar_responsable(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_almacen'         => 'required|integer',
            'id_usuario_empresa' => 'required|integer',
            'fecha_inicio'       => 'required|date',
            'fecha_fin'          => 'nullable|date|after:fecha_inicio',
        ], [
            'id_almacen.required'         => 'El almacén es requerido',
            'id_usuario_empresa.required' => 'El responsable es requerido',
            'fecha_inicio.required'       => 'La fecha de inicio es requerida',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = $this->almacenService->asignar_responsable(
            $request->id_almacen,
            $request->id_usuario_empresa,
            $request->fecha_inicio,
            $request->fecha_fin
        );

        return response()->json($result);
    }

    public function get_responsables_historial(Request $request): JsonResponse
    {
        $id_almacen = $request->query('id_almacen');
        if (!$id_almacen) {
            return response()->json(ApiResponse::error('El id_almacen es requerido'), 400);
        }

        $result = $this->almacenService->get_responsables_historial((int)$id_almacen);
        return response()->json($result);
    }

    public function asignar_labor(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_almacen' => 'required|integer',
            'id_labor'   => 'required|integer',
        ], [
            'id_almacen.required' => 'El almacén es requerido',
            'id_labor.required'   => 'La labor es requerida',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }
        
        $result = $this->almacenService->asignar_labor(
            $request->id_almacen,
            $request->id_labor
        );
        
        return response()->json($result);
    }

    public function get_labores_asignadas(Request $request): JsonResponse
    {
        $id_almacen = $request->query('id_almacen');
        if (!$id_almacen) {
            return response()->json(ApiResponse::error('El id_almacen es requerido'), 400);
        }
        
        $result = $this->almacenService->get_labores_asignadas((int)$id_almacen);
        return response()->json($result);
    }
}
