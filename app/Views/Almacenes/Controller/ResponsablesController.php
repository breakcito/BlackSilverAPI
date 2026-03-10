<?php

namespace App\Views\Almacenes\Controller;

use App\Shared\Responses\ApiResponse;
use App\Views\Almacenes\Service\ResponsablesService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class ResponsablesController extends Controller
{
    public function get_historial_responsables(Request $request, $id_almacen)
    {
        if (! $id_almacen) {
            return response()->json(ApiResponse::error('El almacen es requerido'));
        }

        $result = ResponsablesService::get_historial_responsables((int) $id_almacen);

        return response()->json($result);
    }

    public function nuevo_responsable(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_almacen' => 'required|integer',
            'id_empleado' => 'required|integer',
            'fecha_inicio' => 'required|date',
        ], [
            'id_almacen.required' => 'El almacén es requerido',
            'id_empleado.required' => 'El empleado es requerido',
            'fecha_inicio.required' => 'La fecha de inicio es requerida',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = ResponsablesService::nuevo_responsable(
            $request->id_almacen,
            $request->id_empleado,
            $request->fecha_inicio,
        );

        return response()->json($result);
    }

    public function get_empleados(Request $request, $id_almacen)
    {
        if (! $id_almacen) {
            return response()->json(ApiResponse::error('El almacen es requerido'));
        }

        $result = ResponsablesService::get_empleados((int) $id_almacen);

        return response()->json($result);
    }
}
