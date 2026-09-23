<?php

namespace App\Modules\AlmacenCarbonProveedor\Controllers;

use App\Modules\AlmacenCarbonProveedor\Services\AlmacenCarbonProveedorService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class AlmacenCarbonProveedorController extends Controller
{
    /**
     * Lista los almacenes de carbon del proveedor.
     */
    public function get_almacenes_por_proveedor(int $id_proveedor): JsonResponse
    {
        return response()->json(
            AlmacenCarbonProveedorService::get_por_proveedor($id_proveedor)
        );
    }

    /**
     * Body esperado: { id_departamento?, id_provincia?, id_distrito?, direccion }.
     * direccion obligatorio; los ids de ubigeo son opcionales.
     */
    public function crear_almacen(Request $request, int $id_proveedor): JsonResponse
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

        return response()->json(AlmacenCarbonProveedorService::crear(
            $id_proveedor,
            $request->input('id_departamento') !== null ? (int) $request->input('id_departamento') : null,
            $request->input('id_provincia') !== null ? (int) $request->input('id_provincia') : null,
            $request->input('id_distrito') !== null ? (int) $request->input('id_distrito') : null,
            (string) $request->input('direccion')
        ));
    }

    public function actualizar_almacen(Request $request, int $id_proveedor, int $id_almacen): JsonResponse
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

        return response()->json(AlmacenCarbonProveedorService::actualizar(
            $id_almacen,
            $request->input('id_departamento') !== null ? (int) $request->input('id_departamento') : null,
            $request->input('id_provincia') !== null ? (int) $request->input('id_provincia') : null,
            $request->input('id_distrito') !== null ? (int) $request->input('id_distrito') : null,
            (string) $request->input('direccion')
        ));
    }

    public function eliminar_almacen(int $id_proveedor, int $id_almacen): JsonResponse
    {
        return response()->json(AlmacenCarbonProveedorService::eliminar($id_almacen));
    }
}
