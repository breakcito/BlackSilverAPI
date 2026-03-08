<?php

namespace App\Controllers;

use App\Services\EmpleadoService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class EmpleadoController extends Controller
{
    public function __construct(
        private EmpleadoService $empleadoService
    ) {}

    public function get_empleados(Request $request): JsonResponse
    {
        $result = $this->empleadoService->get_empleados();

        return response()->json($result);
    }

    public function crear_empleado(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_cargo' => 'required|integer',
            'id_empresa' => 'required|integer',
            'nombre' => 'required|string|max:128',
            'apellido' => 'required|string|max:128',
            'dni' => 'nullable|string|size:8',
            'ruc' => 'nullable|string|size:11',
            'carnet_extranjeria' => 'nullable|string|max:64',
            'pasaporte' => 'nullable|string|max:64',
            'fecha_nacimiento' => 'nullable|date',
            'path_foto' => 'nullable|string',
        ], [
            'id_cargo.required' => 'El cargo debe ser seleccionado',
            'id_empresa.required' => 'La empresa debe ser seleccionada',
            'nombre.required' => 'El nombre es obligatorio',
            'apellido.required' => 'El apellido es obligatorio',
            'dni.size' => 'El DNI debe tener 8 caracteres',
            'ruc.size' => 'El RUC debe tener 11 caracteres',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = $this->empleadoService->crear_empleado(
            $request->id_cargo,
            $request->id_empresa,
            $request->nombre,
            $request->apellido,
            $request->dni,
            $request->ruc,
            $request->carnet_extranjeria,
            $request->pasaporte,
            $request->fecha_nacimiento,
            $request->path_foto
        );

        return response()->json($result);
    }
}
