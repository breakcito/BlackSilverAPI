<?php

namespace App\Modules\AlmacenCarbonCliente\Controllers;

use App\Modules\AlmacenCarbonCliente\Services\AlmacenCarbonClienteService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class AlmacenCarbonClienteController extends Controller
{
    /**
     * Lista todos los almacenes de carbon de todos los clientes activos.
     */
    public function get_todos_almacenes(): JsonResponse
    {
        return response()->json(
            AlmacenCarbonClienteService::get_todos_activos()
        );
    }

    /**
     * Lista los almacenes de carbon del cliente.
     */
    public function get_almacenes_por_cliente(int $id_cliente): JsonResponse
    {
        return response()->json(
            AlmacenCarbonClienteService::get_por_cliente($id_cliente)
        );
    }

    /**
     * Body esperado: { id_departamento?, id_provincia?, id_distrito?, direccion }.
     * direccion obligatorio; los ids de ubigeo son opcionales.
     */
    public function crear_almacen(Request $request, int $id_cliente): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_departamento' => 'nullable|integer|min:1',
            'id_provincia' => 'nullable|integer|min:1',
            'id_distrito' => 'nullable|integer|min:1',
            'direccion' => 'required|string|max:256',
        ], [
            'direccion.required' => 'La direccion es obligatoria',
            'direccion.max' => 'La direccion no puede superar 256 caracteres',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        return response()->json(AlmacenCarbonClienteService::crear(
            $id_cliente,
            $request->input('id_departamento') !== null ? (int) $request->input('id_departamento') : null,
            $request->input('id_provincia') !== null ? (int) $request->input('id_provincia') : null,
            $request->input('id_distrito') !== null ? (int) $request->input('id_distrito') : null,
            (string) $request->input('direccion')
        ));
    }

    public function actualizar_almacen(Request $request, int $id_cliente, int $id_almacen): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_departamento' => 'nullable|integer|min:1',
            'id_provincia' => 'nullable|integer|min:1',
            'id_distrito' => 'nullable|integer|min:1',
            'direccion' => 'required|string|max:256',
        ], [
            'direccion.required' => 'La direccion es obligatoria',
            'direccion.max' => 'La direccion no puede superar 256 caracteres',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        return response()->json(AlmacenCarbonClienteService::actualizar(
            $id_almacen,
            $request->input('id_departamento') !== null ? (int) $request->input('id_departamento') : null,
            $request->input('id_provincia') !== null ? (int) $request->input('id_provincia') : null,
            $request->input('id_distrito') !== null ? (int) $request->input('id_distrito') : null,
            (string) $request->input('direccion')
        ));
    }

    public function eliminar_almacen(int $id_cliente, int $id_almacen): JsonResponse
    {
        return response()->json(AlmacenCarbonClienteService::eliminar($id_almacen));
    }
}