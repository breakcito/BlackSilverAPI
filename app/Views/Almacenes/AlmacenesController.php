<?php

namespace App\Views\Almacenes;

use App\Shared\Responses\ApiResponse;
use App\Views\Almacenes\AlmacenesService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AlmacenesController extends Controller
{
    public function __construct(
        private AlmacenesService $service,
    ) {}

    public function get_almacenes(Request $request): JsonResponse
    {
        $result = $this->service->get_almacenes();
        return response()->json($result);
    }

    public function crear_almacen(Request $request)
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

        $result = $this->service->crear_almacen(
            $request->nombre,
            $request->descripcion ?? null,
            $request->es_principal
        );

        return response()->json($result);
    }

    //

    public function get_historial_responsables(Request $request)
    {
        $id_almacen = $request->input('id_almacen');
        if (!$id_almacen) {
            return response()->json(ApiResponse::error('El almacen es requerido'));
        }

        $result = $this->service->get_historial_responsables((int) $id_almacen);

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

        $result = $this->service->nuevo_responsable(
            $request->id_almacen,
            $request->id_empleado,
            $request->fecha_inicio,
        );

        return response()->json($result);
    }

    public function get_empleados(Request $request)
    {
        $id_almacen = $request->input('id_almacen');
        if (!$id_almacen) {
            return response()->json(ApiResponse::error('El almacen es requerido'));
        }

        $result = $this->service->get_empleados((int) $id_almacen);
        return response()->json($result);
    }

    //

    public function get_minas_abastecidas(Request $request)
    {
        $id_almacen = $request->input('id_almacen');
        if (!$id_almacen) {
            return response()->json(ApiResponse::error('El almacen es requerido'));
        }

        $result = $this->service->get_minas_abastecidas((int) $id_almacen);
        return response()->json($result);
    }

    public function nueva_mina_por_abastecer(Request $request)
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

        $result = $this->service->nueva_mina_por_abastecer(
            $request->id_almacen,
            $request->id_mina
        );

        return response()->json($result);
    }

    public function eliminar_abastecimiento_mina(Request $request)
    {
        $id_mina_almacen = $request->input('id_mina_almacen');
        if (!$id_mina_almacen) {
            return response()->json(ApiResponse::error('El id_asignacion es requerido'));
        }

        $result = $this->service->eliminar_abastecimiento_mina($id_mina_almacen);

        return response()->json($result);
    }

    public function get_minas(Request $request)
    {
        $id_almacen = $request->input('id_almacen');
        if (!$id_almacen) {
            return response()->json(ApiResponse::error('El almacen es requerido'));
        }

        $result = $this->service->get_minas((int) $id_almacen);
        return response()->json($result);
    }
}
