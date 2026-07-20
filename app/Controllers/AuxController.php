<?php

namespace App\Controllers;

use App\Services\ActivosFijosService;
use App\Services\AgenciasService;
use App\Services\AlmacenesService;
use App\Services\AreasService;
use App\Services\BancosService;
use App\Services\CargosService;
use App\Services\CategoriasService;
use App\Services\ContratistasService;
use App\Services\EmpleadosService;
use App\Services\EmpresasService;
use App\Services\LaboresService;
use App\Services\LotesMineralService;
use App\Services\LotesProductosService;
use App\Services\MarcasService;
use App\Services\MinasService;
use App\Services\OficinasService;
use App\Services\PersonalExternoService;
use App\Services\ProductosService;
use App\Services\ProveedoresService;
use App\Services\RolesService;
use App\Services\UnidadesMedidaService;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\Periodo;
use App\Shared\Enums\_Generic\TipoBien;
use App\Shared\Enums\_Generic\TipoEntidad;
use App\Shared\Enums\_Generic\TipoProducto;
use App\Shared\Enums\ActivoFijo\EstadoActivoFijo;
use App\Shared\Enums\Contrato\TipoContrato;
use App\Shared\Enums\LoteMineral\EstadoLoteMineral;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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

        if (! $id_almacen || empty($ids_productos) || ! is_array($ids_productos)) {
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
        $id_almacen_excluyente = $request->input('id_almacen_excluyente') ? (int) $request->input('id_almacen_excluyente') : null;
        $id_mina_excluyente = $request->input('id_mina_excluyente') ? (int) $request->input('id_mina_excluyente') : null;
        $con_cuenta = $request->has('con_cuenta') ? $request->boolean('con_cuenta') : null;
        $solo_con_contrato_vigente = $request->has('solo_con_contrato_vigente')
            ? $request->boolean('solo_con_contrato_vigente')
            : null;
        $fecha_fin_programacion = $request->input('fecha_fin_programacion');
        $id_lugar = $request->input('id_lugar') ? (int) $request->input('id_lugar') : null;
        $tipo_lugar = $request->input('tipo_lugar');
        if ($tipo_lugar !== null && ! in_array($tipo_lugar, ['almacen', 'labor', 'oficina'], true)) {
            $tipo_lugar = null;
        }

        $result = EmpleadosService::get_empleados(
            id_empleado: $id_empleado,
            estado: $estado,
            id_almacen_excluyente: $id_almacen_excluyente,
            id_mina_excluyente: $id_mina_excluyente,
            con_cuenta: $con_cuenta,
            solo_con_contrato_vigente: $solo_con_contrato_vigente,
            fecha_fin_programacion: $fecha_fin_programacion,
            id_lugar: $id_lugar,
            tipo_lugar: $tipo_lugar
        );

        return response()->json($result);
    }

    /**
     * Obtener los roles disponibles para asignar
     */
    public function get_roles_disponibles(Request $request): JsonResponse
    {
        $id_rol = $request->input('id_rol') ? (int) $request->input('id_rol') : null;
        $estado_val = $request->input('estado');
        $estado = $estado_val ? EstadoBase::from($estado_val) : EstadoBase::Activo;

        $result = RolesService::get_roles(
            id_rol: $id_rol,
            estado: $estado
        );

        return response()->json($result);
    }

    /**
     * Crear empleado
     */
    public function crear_empleado(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_cargo' => 'nullable|integer',
            'id_contrato_vigente' => 'nullable|integer',
            'con_contrato' => 'nullable|boolean',
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'genero' => 'nullable|string|max:16',
            'dni' => 'nullable|string|max:20',
            'ruc' => 'nullable|string|max:20',
            'carnet_extranjeria' => 'nullable|string|max:20',
            'pasaporte' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'nullable|date',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:128',
            'foto' => 'nullable|image|mimes:jpg,png,jpeg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $id_cargo_input = $request->input('id_cargo');
        $con_contrato = $request->boolean('con_contrato');

        // Si tiene contrato vigente, el id_cargo se gestiona con el contrato (no se requiere elegirlo en este flujo)
        if ($con_contrato) {
            $id_cargo = ! empty($id_cargo_input) ? (int) $id_cargo_input : 0;
        } else {
            if (empty($id_cargo_input)) {
                return response()->json(ApiResponse::error('Debe seleccionar un cargo.'));
            }
            $id_cargo = (int) $id_cargo_input;
        }

        $result = EmpleadosService::crear_empleado(
            id_cargo: $id_cargo,
            nombre: (string) $request->input('nombre'),
            apellido: (string) $request->input('apellido'),
            con_contrato: $con_contrato,
            id_contrato_vigente: $request->input('id_contrato_vigente') ? (int) $request->input('id_contrato_vigente') : null,
            genero: $request->input('genero'),
            dni: $request->input('dni'),
            ruc: $request->input('ruc'),
            carnet_extranjeria: $request->input('carnet_extranjeria'),
            pasaporte: $request->input('pasaporte'),
            fecha_nacimiento: $request->input('fecha_nacimiento'),
            direccion: $request->input('direccion'),
            telefono: $request->input('telefono'),
            email: $request->input('email'),
            foto: $request->file('foto')
        );

        return response()->json($result);
    }

    /**
     * Obtiene las unidades de medida. Acepta filtros opcionales.
     */
    public function get_unidades_medida(Request $request): JsonResponse
    {
        $id_unidad_medida = $request->input('id_unidad_medida') ? (int) $request->input('id_unidad_medida') : null;

        $result = UnidadesMedidaService::get_unidades(
            id_unidad_medida: $id_unidad_medida,
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
        $tipo_entidad = $request->input('tipo_entidad') ? TipoEntidad::from($request->input('tipo_entidad')) : null;
        $para_mantenimiento = $request->input('para_mantenimiento') ? (bool) $request->input('para_mantenimiento') : null;
        $para_transporte = $request->has('para_transporte') ? $request->boolean('para_transporte') : null;

        $result = ProveedoresService::get_proveedores(
            id_proveedor: $id_proveedor,
            estado: $estado,
            tipoEntidad: $tipo_entidad,
            paraMantenimiento: $para_mantenimiento,
            paraTransporte: $para_transporte
        );

        return response()->json($result);
    }

    public function crear_proveedor(Request $request): JsonResponse
    {
        $request->validate([
            'tipo_entidad' => 'required|string',
            'razonSocial' => 'required|string',
            'paraMantenimiento' => 'nullable|boolean',
            'paraTransporte' => 'nullable|boolean',
            'dni' => 'nullable|string',
            'ruc' => 'nullable|string',
            'direccion' => 'nullable|string',
            'telefono' => 'nullable|string',
            'correo' => 'nullable|string',
        ]);

        $tipo_entidad = TipoEntidad::from($request->input('tipo_entidad'));

        $result = ProveedoresService::crear_proveedor(
            tipoEntidad: $tipo_entidad,
            razonSocial: $request->input('razonSocial'),
            paraMantenimiento: $request->input('paraMantenimiento') ?? false,
            paraTransporte: $request->input('paraTransporte') ?? false,
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
        $tipo_bien_excluido = $request->input('tipo_bien_excluido') ? TipoBien::from($request->input('tipo_bien_excluido')) : null;
        $tipo_bien = $request->input('tipo_bien') ? TipoBien::from($request->input('tipo_bien')) : null;

        return response()->json(ProductosService::get_productos(
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
        $id_contratista = $request->input('id_contratista') ? (int) $request->input('id_contratista') : null;

        return response()->json(ContratistasService::get_contratistas(id_mina: $id_mina, id_contratista: $id_contratista));
    }

    public function get_minas(Request $request): JsonResponse
    {
        $id_mina = $request->input('id_mina') ? (int) $request->input('id_mina') : null;
        $id_concesion = $request->input('id_concesion') ? (int) $request->input('id_concesion') : null;
        $id_empleado_responsable = $request->input('id_empleado_responsable') ? (int) $request->input('id_empleado_responsable') : null;
        $id_almacen_abastece = $request->input('id_almacen_abastece') ? (int) $request->input('id_almacen_abastece') : null;

        return response()->json(MinasService::get_minas(
            id_mina: $id_mina,
            id_concesion: $id_concesion,
            id_empleado_responsable: $id_empleado_responsable,
            id_almacen_abastece: $id_almacen_abastece
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
        $id_contratista_excluyente = $request->input('id_contratista_excluyente') ? (int) $request->input('id_contratista_excluyente') : null;

        return response()->json(LaboresService::get_labores(
            id_mina: $id_mina,
            id_labor: $id_labor,
            id_contratista_excluyente: $id_contratista_excluyente
        ));
    }

    /**
     * Catálogo de áreas.
     */
    public function get_areas(Request $request): JsonResponse
    {
        $id_area = $request->input('id_area') ? (int) $request->input('id_area') : null;
        $estado_val = $request->input('estado');
        $estado = $estado_val ? EstadoBase::from($estado_val) : EstadoBase::Activo;
        $con_cargos = (bool) $request->input('con_cargos', false);

        return response()->json(AreasService::get_areas(
            id_area: $id_area,
            estado: $estado,
            con_cargos: $con_cargos
        ));
    }

    /**
     * Catálogo de cargos.
     */
    public function get_cargos(Request $request): JsonResponse
    {
        $id_cargo = $request->input('id_cargo') ? (int) $request->input('id_cargo') : null;
        $id_area = $request->input('id_area') ? (int) $request->input('id_area') : null;
        $estado_val = $request->input('estado');
        $estado = $estado_val ? EstadoBase::from($estado_val) : EstadoBase::Activo;
        $con_area = $request->has('con_area')
            ? filter_var($request->input('con_area'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;

        return response()->json(CargosService::get_cargos(
            id_cargo: $id_cargo,
            id_area: $id_area,
            estado: $estado,
            con_area: $con_area
        ));
    }

    /**
     * Catálogo de bancos.
     */
    public function get_bancos(Request $request): JsonResponse
    {
        return response()->json(BancosService::get_bancos());
    }

    /**
     * Crear banco.
     */
    public function crear_banco(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'abreviatura' => 'required|string|max:20',
        ]);

        return response()->json(BancosService::crear_banco(
            nombre: (string) $request->input('nombre'),
            abreviatura: (string) $request->input('abreviatura')
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
            return_object: true
        );

        return response()->json($result);
    }

    /**
     * Catálogo de tipos de contrato disponibles (Planilla / JornadaDiaria).
     */
    public function get_tipos_contrato(Request $request): JsonResponse
    {
        $tipos = array_map(
            fn (TipoContrato $c) => [
                'value' => $c->value,
                'label' => $c->value === TipoContrato::Planilla->value ? 'Planilla' : 'Jornada Diaria',
            ],
            TipoContrato::cases()
        );

        return response()->json(ApiResponse::success($tipos));
    }

    /**
     * Catálogo de periodos para duración de contrato (excluyendo Semanal y Ninguno).
     */
    public function get_periodos_duracion(Request $request): JsonResponse
    {
        $excluidos = ['semanal', 'ninguno'];
        $periodos = array_values(array_filter(
            Periodo::cases(),
            fn (Periodo $p) => ! in_array($p->value, $excluidos, true)
        ));

        $data = array_map(
            fn (Periodo $p) => [
                'value' => $p->value,
                'label' => ucfirst($p->value),
            ],
            $periodos
        );

        return response()->json(ApiResponse::success($data));
    }

    public function get_lotes_mineral(Request $request): JsonResponse
    {
        $id_lote_mineral = $request->input('id_lote_mineral') ? (int) $request->input('id_lote_mineral') : null;
        $id_contratista = $request->input('id_contratista') ? (int) $request->input('id_contratista') : null;
        $id_mina = $request->input('id_mina') ? (int) $request->input('id_mina') : null;
        $id_labor = $request->input('id_labor') ? (int) $request->input('id_labor') : null;
        $estado = $request->input('estado') ? EstadoLoteMineral::from($request->input('estado')) : EstadoLoteMineral::EnProduccion;

        $result = LotesMineralService::get_lotes_mineral(
            id_lote_mineral: $id_lote_mineral,
            id_contratista: $id_contratista,
            id_mina: $id_mina,
            id_labor: $id_labor,
            estado: $estado
        );

        return response()->json($result);
    }

    public function get_agencias_transporte(): JsonResponse
    {
        $result = AgenciasService::get_agencias();

        return response()->json($result);
    }

    public function crear_agencia_transporte(Request $request): JsonResponse
    {
        $request->validate([
            'razon_social' => 'required|string|max:255',
        ]);

        $result = AgenciasService::crear_agencia(
            razon_social: $request->input('razon_social'),
            return_object: true
        );

        return response()->json($result);
    }

    public function get_oficinas(Request $request): JsonResponse
    {
        $id_oficina = $request->input('id_oficina') ? (int) $request->input('id_oficina') : null;
        $id_empresa = $request->input('id_empresa') ? (int) $request->input('id_empresa') : null;
        $estado = $request->input('estado') ? EstadoBase::from($request->input('estado')) : EstadoBase::Activo;
        $result = OficinasService::get_oficinas(
            id_oficina: $id_oficina,
            id_empresa: $id_empresa,
            estado: $estado
        );

        return response()->json($result);
    }
}
