<?php

namespace App\Controllers;

use App\Services\ActivosFijosService;
use App\Services\AlmacenesService;
use App\Services\CategoriasService;
use App\Services\EmpleadosService;
use App\Services\EmpresasService;
use App\Services\LotesProductosService;
use App\Services\MarcasService;
use App\Services\MinasService;
use App\Services\PersonalExternoService;
use App\Services\ProductosService;
use App\Services\ProveedoresService;
use App\Services\UnidadesMedidaService;
use App\Services\LaboresService;
use App\Modules\Contratistas\Service\ContratistasService;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\Periodo;
use App\Shared\Enums\_Generic\TipoBien;
use App\Shared\Enums\_Generic\TipoEntidad;
use App\Shared\Enums\_Generic\TipoProducto;
use App\Shared\Enums\ActivoFijo\EstadoActivoFijo;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;


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
        $id_personal = $request->input('id_personal') ? (int) $request->input('id_personal') : null;
        $id_proveedor = $request->input('id_proveedor') ? (int) $request->input('id_proveedor') : null;
        $estado_val = $request->input('estado');
        $estado = $estado_val ? EstadoBase::from($estado_val) : null;

        $result = PersonalExternoService::get_personal(
            id_personal: $id_personal,
            id_proveedor: $id_proveedor,
            estado: $estado
        );
        return response()->json($result);
    }

    public function crear_personal_externo(Request $request): JsonResponse
    {
        $request->validate([
            'id_proveedor' => 'nullable|int',
            'nombre' => 'required|string',
            'apellido' => 'nullable|string',
            'dni' => 'nullable|string',
        ]);

        $result = PersonalExternoService::crear_personal(
            id_proveedor: $request->input('id_proveedor') ? (int) $request->input('id_proveedor') : null,
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
        $para_mantenimiento = $request->input('para_mantenimiento') ? (bool) $request->input('para_mantenimiento') : null;

        $result = ProveedoresService::get_proveedores(
            id_proveedor: $id_proveedor,
            estado: $estado,
            tipoEntidad: $tipo_entidad,
            paraMantenimiento: $para_mantenimiento
        );

        return response()->json($result);
    }

    public function crear_proveedor(Request $request): JsonResponse
    {
        $request->validate([
            'tipo_entidad' => 'required|string',
            'razonSocial' => 'required|string',
            'paraMantenimiento' => 'nullable|boolean',
            'dni' => 'nullable|string',
            'ruc' => 'nullable|string',
            'direccion' => 'nullable|string',
            'telefono' => 'nullable|string',
            'correo' => 'nullable|string'
        ]);

        $tipo_entidad = TipoEntidad::from($request->input('tipo_entidad'));

        $result = ProveedoresService::crear_proveedor(
            tipoEntidad: $tipo_entidad,
            razonSocial: $request->input('razonSocial'),
            paraMantenimiento: $request->input('paraMantenimiento') ?? false,
            dni: $request->input('dni'),
            ruc: $request->input('ruc'),
            direccion: $request->input('direccion'),
            telefono: $request->input('telefono'),
            correo: $request->input('correo'),
            return_object: true
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

    /**
     * Crear un nuevo producto
     */
    public function crear_producto(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_categoria' => 'required|integer',
            'id_unidad_medida_base' => 'required|integer',
            'nombre' => 'required|string|max:128',
            'prefijo' => 'nullable|string|max:4',
            'es_auditable' => 'required|boolean',
            'para_mantenimiento' => 'required|boolean',
            'es_perecible' => 'required|boolean',
            'stock_minimo_base' => 'nullable|numeric|min:0',
            'costo_promedio_base' => 'nullable|numeric|min:0',
            'tiempo_espera_vencimiento' => 'nullable|integer|min:0',
            'periodo_espera_vencimiento' => ['nullable', new Enum(Periodo::class)],
        ], [
            'id_categoria.required' => 'La categoría es requerida',
            'id_unidad_medida_base.required' => 'La unidad de medida es requerida',
            'nombre.required' => 'El nombre es requerido',
            'es_auditable.required' => 'Debe indicar si es auditable',
            'es_perecible.required' => 'Debe indicar si es perecible',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = ProductosService::crear_producto(
            id_categoria: $request->integer('id_categoria'),
            id_unidad_medida_base: $request->integer('id_unidad_medida_base'),
            nombre: $request->string('nombre'),
            prefijo: $request->input('prefijo'),
            es_auditable: $request->boolean('es_auditable'),
            es_perecible: $request->boolean('es_perecible'),
            para_mantenimiento: $request->boolean('para_mantenimiento'),
            stock_minimo_base: (float) ($request->input('stock_minimo_base') ?? 0),
            costo_promedio_base: (float) ($request->input('costo_promedio_base') ?? 0),
            tiempo_espera_vencimiento: $request->input('tiempo_espera_vencimiento') ? (int) $request->input('tiempo_espera_vencimiento') : null,
            periodo_espera_vencimiento: $request->input('periodo_espera_vencimiento')
        );

        return response()->json($result);
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

    /**
     * Catálogo de contratistas. Acepta filtro opcional por mina.
     */
    public function get_contratistas(Request $request): JsonResponse
    {
        $id_mina = $request->input('id_mina') ? (int) $request->input('id_mina') : null;

        return response()->json(ContratistasService::get_contratistas(id_mina: $id_mina));
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
            $ids_productos = array_map('intval', array_filter($raw_producto));
        } else {
            // Si es un entero/string único, lo casteamos directamente si no está vacío
            $ids_productos = ($raw_producto !== null && $raw_producto !== '') ? (int) $raw_producto : null;
        }

        $para_transporte = $request->has('para_transporte') ? $request->boolean('para_transporte') : null;
        $control_por_odometro = $request->has('control_por_odometro') ? $request->boolean('control_por_odometro') : null;
        $control_por_horometro = $request->has('control_por_horometro') ? $request->boolean('control_por_horometro') : null;
        $control_por_vueltas = $request->has('control_por_vueltas') ? $request->boolean('control_por_vueltas') : null;

        $estado_val = $request->input('estado');
        $estado = $estado_val ? EstadoActivoFijo::from($estado_val) : null;

        return response()->json(ActivosFijosService::get_activos_disponibles(
            ids_productos: $ids_productos,
            id_activo: $id_activo,
            id_almacen: $id_almacen,
            id_mina: $id_mina,
            para_transporte: $para_transporte,
            control_por_odometro: $control_por_odometro,
            control_por_horometro: $control_por_horometro,
            control_por_vueltas: $control_por_vueltas,
            estado: $estado,
        ));
    }

    /**
     * Catálogo de labores. Acepta filtros opcionales.
     */
    public function get_labores(Request $request): JsonResponse
    {
        $id_mina = $request->input('id_mina') ? (int) $request->input('id_mina') : null;
        $id_labor = $request->input('id_labor') ? (int) $request->input('id_labor') : null;
        $id_requerimiento = $request->input('id_requerimiento') ? (int) $request->input('id_requerimiento') : null;

        return response()->json(LaboresService::get_labores(
            id_mina: $id_mina,
            id_labor: $id_labor,
            id_requerimiento: $id_requerimiento
        ));
    }

    /**Listar categorias */
    public function get_categorias(Request $request): JsonResponse
    {
        $id_categoria = $request->input('id_categoria') ? (int) $request->input('id_categoria') : null;
        $estado_val = $request->input('estado');
        $estado = $estado_val ? EstadoBase::from($estado_val) : null;
        $result = CategoriasService::get_categorias(id_categoria: $id_categoria, estado: $estado);
        return response()->json($result);
    }

    /**
     * Crear una nueva categoría desde la capa global
     */
    public function crear_categoria(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:128',
            'descripcion' => 'nullable|string',
            'tipo_producto' => ['required', new Enum(TipoProducto::class)],
            'clasificacion_bien' => ['required', new Enum(TipoBien::class)],
            'para_transporte' => 'boolean',
            'control_por_odometro' => 'boolean',
            'control_por_horometro' => 'boolean',
            'control_por_vueltas' => 'boolean',
            'es_consumible' => 'boolean',
            'para_cocina' => 'boolean',
            'para_mina' => 'boolean',
            'es_auditable' => 'boolean',
            'ids_categorias_consumidoras' => 'array',
            'ids_categorias_consumidoras.*' => 'integer',
        ], [
            'nombre.required' => 'El nombre es obligatorio',
            'tipo_producto.required' => 'El tipo de requerimiento es obligatorio',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = CategoriasService::crear_categoria(
            nombre: (string) $request->input('nombre'),
            tipo_producto: TipoProducto::from($request->input('tipo_producto')),
            descripcion: $request->input('descripcion'),
            clasificacion_bien: TipoBien::from($request->input('clasificacion_bien')),
            para_transporte: (bool) $request->boolean('para_transporte'),
            control_por_odometro: (bool) $request->boolean('control_por_odometro'),
            control_por_horometro: (bool) $request->boolean('control_por_horometro'),
            control_por_vueltas: (bool) $request->boolean('control_por_vueltas'),
            es_consumible: (bool) $request->boolean('es_consumible'),
            para_cocina: (bool) $request->boolean('para_cocina'),
            para_mina: (bool) $request->boolean('para_mina'),
            es_auditable: (bool) $request->boolean('es_auditable'),
            ids_categorias_consumidoras: (array) $request->input('ids_categorias_consumidoras', []),
            return_object: true
        );

        return response()->json($result);
    }
}
