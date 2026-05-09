<?php

namespace App\Controllers;

use App\Services\AlmacenesService;
use App\Services\EmpleadosService;
use App\Services\LotesProductosService;
use App\Services\PersonalExternoService;
use App\Services\UnidadesMedidaService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;

class AuxController extends Controller
{
    /**
     * Catálogo de almacenes para el módulo de OC.
     */
    public function get_almacenes(): JsonResponse
    {
        return response()->json(AlmacenesService::get_almacenes());
    }

    /**
     * Obtener lotes disponibles en el almacén de destino para productos de OC.
     */
    public function get_lotes_disponibles(Request $request): JsonResponse
    {
        $id_almacen = (int) $request->input('id_almacen_recepcionista');
        $id_productos = $request->input('id_productos');

        if (!$id_almacen || empty($id_productos) || !is_array($id_productos)) {
            return response()->json(ApiResponse::error('ID de almacén y arreglo de productos son requeridos'), 400);
        }

        return response()->json(LotesProductosService::get_lotes_disponibles($id_almacen, $id_productos));
    }

    public function get_personal_externo(Request $request): JsonResponse
    {
        $result = PersonalExternoService::get_personal();
        return response()->json($result);
    }

    public function crear_personal_externo(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string',
            'apellido' => 'nullable|string',
            'dni' => 'nullable|string',
        ]);

        $result = PersonalExternoService::crear_personal(
            nombre: $request->input('nombre'),
            apellido: $request->input('apellido'),
            dni: $request->input('dni')
        );

        return response()->json($result);
    }

    public function get_empleados(Request $request): JsonResponse
    {
        $result = EmpleadosService::get_empleados();

        return response()->json($result);
    }

    /**
     * Obtiene las unidades de medida
     */
    public function get_unidades_medida(): JsonResponse
    {
        $result = UnidadesMedidaService::get_unidades();
        return response()->json($result);
    }

}
