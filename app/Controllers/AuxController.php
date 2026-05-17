<?php

namespace App\Controllers;

use App\Services\ActivosFijosService;
use App\Services\AlmacenesService;
use App\Services\EmpleadosService;
use App\Services\EmpresasService;
use App\Services\LotesProductosService;
use App\Services\MarcasService;
use App\Services\MinasService;
use App\Services\PersonalExternoService;
use App\Services\ProductosService;
use App\Services\ProveedoresService;
use App\Services\UnidadesMedidaService;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\TipoBien;
use App\Shared\Enums\ActivoFijo\EstadoActivoFijo;
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

        if (!$id_almacen || empty($ids_productos) || !is_array($ids_productos)) {
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
     * Obtener proveedores habilitados
     */
    public function get_proveedores(Request $request): JsonResponse
    {
        $id_proveedor = $request->input('id_proveedor') ? (int) $request->input('id_proveedor') : null;
        $estado_val = $request->input('estado');
        $estado = $estado_val ? EstadoBase::from($estado_val) : null;
        $tipo_entidad = $request->input('tipo_entidad');

        $result = ProveedoresService::get_proveedores(
            id_proveedor: $id_proveedor,
            estado: $estado,
            tipoEntidad: $tipo_entidad
        );

        return response()->json($result);
    }

    /**
     * Catálogo de productos.
     */
    public function get_productos(Request $request): JsonResponse
    {
        $con_categorias = (bool) $request->input('con_categorias_consumidoras', false);
        $tipo_bien_excluido = $request->input('tipo_bien_excluido') ? TipoBien::from($request->input('tipo_bien_excluido')) : null;
        $tipo_bien = $request->input('tipo_bien') ? TipoBien::from($request->input('tipo_bien')) : null;
        return response()->json(ProductosService::get_productos(
            con_categorias_consumidoras: $con_categorias,
            tipo_bien_excluido: $tipo_bien_excluido,
            tipo_bien: $tipo_bien
        ));
    }

    public function get_empresas(Request $request): JsonResponse
    {
        $id_empresa = $request->input('id_empresa') ? (int) $request->input('id_empresa') : null;
        $estado_val = $request->input('estado');
        $estado = $estado_val ? EstadoBase::from($estado_val) : null;

        return response()->json(EmpresasService::get_empresas(
            id_empresa: $id_empresa,
            estado: $estado
        ));
    }

    public function get_minas(Request $request): JsonResponse
    {
        $id_mina = $request->input('id_mina') ? (int) $request->input('id_mina') : null;
        $id_concesion = $request->input('id_concesion') ? (int) $request->input('id_concesion') : null;
        $id_contratista_responsable = $request->input('id_contratista_responsable') ? (int) $request->input('id_contratista_responsable') : null;

        return response()->json(MinasService::get_minas(
            id_mina: $id_mina,
            id_concesion: $id_concesion,
            id_contratista_responsable: $id_contratista_responsable
        ));
    }

    public function get_marcas(Request $request): JsonResponse
    {
        $id_marca = $request->input('id_marca') ? (int) $request->input('id_marca') : null;
        $estado_val = $request->input('estado');
        $estado = $estado_val ? EstadoBase::from($estado_val) : null;
        return response()->json(MarcasService::get_marcas(id_marca: $id_marca, estado: $estado));
    }

    public function crear_marca(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string',
        ]);

        $result = MarcasService::crear_marca(
            nombre: $request->input('nombre')
        );

        return response()->json($result);
    }


    /**
     * Obtener solo los activos fijos
     * que esten disponibles segun se requiere.
     */
    public function get_activos_disponibles(Request $request): JsonResponse
    {
        $id_activo = $request->input('id_activo') ? (int) $request->input('id_activo') : null;
        $id_almacen = $request->input('id_almacen') ? (int) $request->input('id_almacen') : null;
        $id_mina = $request->input('id_mina') ? (int) $request->input('id_mina') : null;

        // 1. Capturar y normalizar el input (puede venir de un query string o JSON)
        $raw_producto = $request->input('ids_productos');

        if (is_array($raw_producto)) {
            // Si es un array, limpiamos y casteamos cada elemento a entero
            $id_producto = array_map('intval', array_filter($raw_producto));
        } else {
            // Si es un entero/string único, lo casteamos directamente si no está vacío
            $id_producto = ($raw_producto !== null && $raw_producto !== '') ? (int) $raw_producto : null;
        }

        $para_transporte = $request->input('para_transporte') ? (bool) $request->input('para_transporte') : null;
        $control_por_odometro = $request->input('control_por_odometro') ? (bool) $request->input('control_por_odometro') : null;
        $control_por_horometro = $request->input('control_por_horometro') ? (bool) $request->input('control_por_horometro') : null;
        $estado_val = $request->input('estado');
        $estado = $estado_val ? EstadoActivoFijo::from($estado_val) : null;

        return response()->json(ActivosFijosService::get_activos_disponibles(
            id_activo: $id_activo,
            id_almacen: $id_almacen,
            id_mina: $id_mina,
            id_producto: $id_producto, // 2. Corregido: Coincide con el parámetro del Service
            para_transporte: $para_transporte,
            control_por_odometro: $control_por_odometro,
            control_por_horometro: $control_por_horometro,
            estado: $estado,
        ));
    }
}
