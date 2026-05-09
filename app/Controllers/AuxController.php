<?php

namespace App\Controllers;

use App\Services\AlmacenesService;
use App\Services\EmpleadosService;
use App\Services\LotesProductosService;
use App\Services\PersonalExternoService;
use App\Services\ProductosService;
use App\Services\UnidadesMedidaService;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;

class AuxController extends Controller
{
    /**
     * Catálogo de almacenes. Acepta filtros opcionales.
     */
    public function get_almacenes(Request $request): JsonResponse
    {
        $id_almacen = $request->input('id_almacen') ? (int) $request->input('id_almacen') : null;
        $id_empleado_responsable = $request->input('id_empleado_responsable') ? (int) $request->input('id_empleado_responsable') : null;
        $es_principal = $request->has('es_principal') ? (int) $request->input('es_principal') : null;

        return response()->json(AlmacenesService::get_almacenes(
            id_almacen: $id_almacen,
            id_empleado_responsable: $id_empleado_responsable,
            es_principal: $es_principal
        ));
    }

    /**
     * Obtener lotes disponibles en el almacén de destino para productos de OC.
     */
    public function get_lotes_disponibles(Request $request): JsonResponse
    {
        $id_almacen = (int) $request->input('id_almacen');
        $ids_productos = $request->input('ids_productos');

        if (!$id_almacen || empty($id_productos) || !is_array($ids_productos)) {
            return response()->json(ApiResponse::error('ID de almacén y arreglo de productos son requeridos'), 400);
        }

        return response()->json(LotesProductosService::get_lotes_disponibles($id_almacen, $ids_productos));
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
        $id_empleado = $request->input('id_empleado') ? (int) $request->input('id_empleado') : null;
        $estado_val = $request->input('estado');
        $estado = $estado_val ? EstadoBase::from($estado_val) : EstadoBase::Activo;

        $result = EmpleadosService::get_empleados(
            id_empleado: $id_empleado,
            estado: $estado
        );

        return response()->json($result);
    }

    /**
     * Obtiene las unidades de medida. Acepta filtros opcionales.
     */
    public function get_unidades_medida(Request $request): JsonResponse
    {
        $id_unidad_medida = $request->input('id_unidad_medida') ? (int) $request->input('id_unidad_medida') : null;
        $solo_base = $request->has('solo_base') ? (int) $request->input('solo_base') : null;

        $result = UnidadesMedidaService::get_unidades(
            id_unidad_medida: $id_unidad_medida,
            solo_base: $solo_base
        );

        return response()->json($result);
    }

    /**
     * Catálogo de productos.
     */
    public function get_productos(Request $request): JsonResponse
    {
        $con_categorias = (bool) $request->input('con_categorias_consumidoras', false);

        return response()->json(ProductosService::get_productos(
            con_categorias_consumidoras: $con_categorias
        ));
    }

}
