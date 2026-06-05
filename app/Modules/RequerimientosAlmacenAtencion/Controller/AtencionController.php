<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Controller;

use App\Shared\Enums\_Generic\Premura;
use App\Shared\Responses\ApiResponse;
use App\Modules\RequerimientosAlmacenAtencion\Service\AtencionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class AtencionController extends Controller
{
    /**
     * ------------------------------------------------------
     * PARA LA CABECERA
     * ------------------------------------------------------
     */


    /**
     * Listado de requerimientos para atención por almacén.
     */
    public function get_requerimientos(Request $request): JsonResponse
    {
        $id_almacen = $request->input('id_almacen');
        $mes = $request->input('mes');
        $yearcito = $request->input('yearcito');

        if (!$id_almacen || !$mes || !$yearcito) {
            return response()->json(ApiResponse::error('id_almacen, mes y yearcito son requeridos'), 400);
        }

        $result = AtencionService::get_requerimientos((int) $id_almacen, $mes, $yearcito);

        return response()->json($result);
    }

    public function crear_requerimiento(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $reglas = [
            'id_contratista_solicitante' => 'required|integer',
            'id_mina' => 'required|integer',
            'id_almacen_destino' => 'required|integer',
            'es_auditable' => 'required|boolean',
            'premura' => 'required|string',
            'fecha_entrega_requerida' => 'required|date',
            'observacion' => 'nullable|string',
            'labores' => 'nullable|array',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_producto' => 'required|integer',
            'detalles.*.id_unidad_medida' => 'required|integer',
            'detalles.*.cantidad_solicitada' => 'required|numeric|min:0.01',
            'detalles.*.contenido_por_presentacion' => 'required|numeric|min:0.01',
            'detalles.*.comentario' => 'nullable|string',
            'evidencias' => 'nullable|array',
            'evidencias.*' => 'file',
        ];

        $validator = Validator::make($request->all(), $reglas);

        if ($validator->fails()) {
            $errores = $validator->errors()->all();
            return response()->json(ApiResponse::error('Datos inválidos: ' . implode(', ', $errores)));
        }

        $id_empleado_registro = $authUser->id_empleado;
        $evidencias = $request->file('evidencias', []);

        $premura = Premura::from($request->input('premura'));
        try {
            $resultado = AtencionService::registrar_requerimiento(
                id_contratista_solicitante: (int) $request->id_contratista_solicitante,
                id_empleado_registro: (int) $id_empleado_registro,
                id_mina: (int) $request->id_mina,
                id_almacen_destino: (int) $request->id_almacen_destino,
                es_auditable: (bool) $request->es_auditable,
                premura: $premura,
                observacion: $request->observacion,
                fecha_entrega_requerida: $request->fecha_entrega_requerida,
                labores: $request->labores,
                detalles: $request->detalles,
                evidencias: $evidencias
            );

            return response()->json($resultado);
        } catch (\Exception $e) {
            return response()->json(ApiResponse::error('Error al registrar requerimiento: ' . $e->getMessage()), 500);
        }
    }


    /**
     * ------------------------------------------------------
     * PARA EL DETALLE
     * ------------------------------------------------------
     */


    /**
     * Obtener los detalles de un requerimiento.
     */
    public function get_detalles_requerimiento(Request $request): JsonResponse
    {
        $id = $request->input('id_requerimiento');
        if (!$id) {
            return response()->json(ApiResponse::error('El id_requerimiento es requerido'), 400);
        }

        $result = AtencionService::get_detalles_requerimiento((int) $id);

        return response()->json($result);
    }

    /**
     * Aprobar o Rechazar uno o varios ítems del requerimiento.
     */
    public function update_estado_detalle_requerimiento(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_requerimiento_almacen_detalle' => 'nullable|integer', // Retrocompatibilidad
            'ids_detalles' => 'nullable|array',                     // Nuevo: Masivo
            'ids_detalles.*' => 'integer',
            'nuevo_estado' => 'required|string',
            'comentario_decision' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        // Normalizar los IDs a un solo arreglo para el servicio
        $ids = [];
        if ($request->has('id_requerimiento_almacen_detalle')) {
            $ids[] = (int) $request->id_requerimiento_almacen_detalle;
        }
        if ($request->has('ids_detalles')) {
            $ids = array_merge($ids, $request->ids_detalles);
        }

        // Eliminar duplicados si los hubiera
        $ids = array_unique($ids);

        if (empty($ids)) {
            return response()->json(ApiResponse::error('Debe proporcionar al menos un ID de detalle'), 400);
        }

        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $result = AtencionService::cambiar_estado_detalle(
            $authUser->id_empleado,
            $ids,
            $request->nuevo_estado,
            $request->comentario_decision
        );

        return response()->json($result);
    }

    /**
     * Obtener trazabilidad de un detalle de requerimiento.
     */
    public function get_trazabilidad(Request $request): JsonResponse
    {
        $id_detalle = $request->input('id_requerimiento_almacen_detalle');
        if (!$id_detalle) {
            return response()->json(ApiResponse::error('El id_requerimiento_almacen_detalle es requerido'), 400);
        }

        $result = AtencionService::obtener_trazabilidad((int) $id_detalle);

        return response()->json($result);
    }
}
