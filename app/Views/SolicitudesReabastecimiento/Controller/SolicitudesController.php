<?php

namespace App\Views\SolicitudesReabastecimiento\Controller;

use App\Shared\Responses\ApiResponse;
use App\Views\SolicitudesReabastecimiento\Service\EntregasService;
use App\Views\SolicitudesReabastecimiento\Service\SolicitudesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class SolicitudesController extends Controller
{
    // Obtener todas la lista de solicitudes en base a mes y año
    public function get_solicitudes(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        $validator = Validator::make($request->all(), [
            'mes' => 'required|integer|between:1,12',
            'yearcito' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $result = SolicitudesService::get_solicitudes(
            (int) $authUser->id_empleado,
            (int) $request->mes,
            (int) $request->yearcito,
        );
        return response()->json($result);
    }

    // Registrar una solicitud y sus detalles
    public function crear_solicitud(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_almacen_solicitante' => 'required|integer',
            'premura' => 'required|string',
            'observacion' => 'nullable|string',
            'fecha_entrega_requerida' => 'nullable|string',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_producto' => 'required|integer',
            'detalles.*.id_unidad_medida' => 'required|integer',
            'detalles.*.cantidad_solicitada' => 'required|numeric|min:0.01',
            'detalles.*.contenido_por_presentacion' => 'required|numeric|min:0.01',
            'detalles.*.comentario' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $result = SolicitudesService::crear_solicitud(
            (int) $request->id_almacen_solicitante,
            (int) $authUser->id_empleado,
            $request->premura,
            $request->observacion,
            $request->fecha_entrega_requerida,
            $request->detalles
        );

        return response()->json($result);
    }

    // Obtener los detalles de una solicitud
    public function get_detalles_solicitud(Request $request): JsonResponse
    {
        $id_solicitud = $request->input('id_solicitud_reabastecimiento');
        if (!$id_solicitud) {
            return response()->json(ApiResponse::error('El id_solicitud_reabastecimiento es requerido'), 400);
        }

        $result = SolicitudesService::get_detalles_solicitud((int) $id_solicitud);
        return response()->json($result);
    }

    // Obtener la trazabilidad de un detalle
    public function get_trazabilidad_by_detalle(Request $request): JsonResponse
    {
        $id_detalle = $request->input('id_solicitud_detalle');
        if (!$id_detalle) {
            return response()->json(ApiResponse::error('El id_solicitud_detalle es requerido'), 400);
        }

        $result = SolicitudesService::get_trazabilidad_by_detalle((int) $id_detalle);
        return response()->json($result);
    }

    // Obtener historial de entregas
    public function get_historial_entregas(Request $request): JsonResponse
    {
        $id_solicitud = $request->input('id_solicitud_reabastecimiento');
        if (!$id_solicitud) {
            return response()->json(ApiResponse::error('El id_solicitud_reabastecimiento es requerido'), 400);
        }

        $result = EntregasService::get_historial_entregas((int) $id_solicitud);
        return response()->json($result);
    }
    // Recibir múltiples detalles de una entrega a la vez
    public function recibir_entrega_item(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_reabastecimiento_entrega' => 'required|integer',
            'tipo_entrega' => 'nullable|string|in:Solicitud,Prestamo',
            'items' => 'required|array|min:1',
            'items.*.id_solicitud_reabastecimiento_detalle' => 'required|integer',
            'items.*.es_nuevo_lote' => 'required|boolean',
            'items.*.cantidad_base' => 'required|numeric|min:0.01',
            'items.*.id_lote_existente' => 'nullable|integer',
            'items.*.fecha_vencimiento' => 'nullable|date',
            'items.*.id_unidad_medida' => 'nullable|integer',
            'items.*.contenido_por_presentacion' => 'nullable|numeric|min:0.01',
            'items.*.fecha_ingreso' => 'nullable|date',
            'items.*.descripcion' => 'nullable|string',
            // Nuevos campos de cabecera (se aplican a toda la recepción)
            'con_incidencia' => 'nullable|boolean',
            'observacion' => 'required_if:con_incidencia,true|nullable|string',
            'evidencias' => 'required_if:con_incidencia,true|nullable|array',
            'fecha_hora_recepcion' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        // Validación condicional
        foreach ($request->input('items') as $item) {
            if (!$item['es_nuevo_lote'] && empty($item['id_lote_existente'])) {
                return response()->json(ApiResponse::error('Debe seleccionar un lote existente para ajustar su stock'), 400);
            }
        }

        $result = EntregasService::recibir_entregas(
            (int) $request->input('id_reabastecimiento_entrega'),
            $request->input('items'),
            (string) $request->input('tipo_entrega', 'Solicitud'),
            (bool) $request->input('con_incidencia', false),
            $request->input('observacion'),
            $request->file('evidencias') ?? [],
            $request->input('fecha_hora_recepcion')
        );

        return response()->json($result);
    }

    public function recibir_entrega_bulk(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'recepciones' => 'required|array|min:1',
            'recepciones.*.id_reabastecimiento_entrega' => 'required|integer',
            'recepciones.*.tipo_entrega' => 'nullable|string|in:Solicitud,Prestamo',
            'recepciones.*.items' => 'required|array|min:1',
            'recepciones.*.items.*.id_solicitud_reabastecimiento_detalle' => 'required|integer',
            'recepciones.*.items.*.es_nuevo_lote' => 'required|in:0,1,true,false',
            'recepciones.*.items.*.cantidad_base' => 'required|numeric|min:0.01',
            'recepciones.*.items.*.id_lote_existente' => 'nullable|integer',
            'recepciones.*.items.*.fecha_vencimiento' => 'nullable|date',
            'recepciones.*.items.*.id_unidad_medida' => 'nullable|integer',
            'recepciones.*.items.*.contenido_por_presentacion' => 'nullable|numeric|min:0.01',
            'recepciones.*.items.*.fecha_ingreso' => 'nullable|date',
            'recepciones.*.items.*.descripcion' => 'nullable|string',
            // Campos raíz
            'id_empleado_registro' => 'required|integer',
            'evidencias' => 'nullable|array',
            'evidencias.*' => 'nullable|file',
            // Nuevos campos por recepción
            'recepciones.*.con_incidencia' => 'nullable|in:0,1,true,false',
            'recepciones.*.observacion' => 'nullable|string',
            'recepciones.*.fecha_hora_recepcion' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        // Validación condicional
        foreach ($request->input('recepciones') as $recepcion) {
            foreach ($recepcion['items'] as $item) {
                if (!$item['es_nuevo_lote'] && empty($item['id_lote_existente'])) {
                    return response()->json(ApiResponse::error('Debe seleccionar un lote existente para ajustar su stock en el detalle ' . $item['id_solicitud_reabastecimiento_detalle']), 400);
                }
            }
        }

        $result = EntregasService::recibir_entregas_bulk(
            $request->input('recepciones'),
            (int) $request->input('id_empleado_registro'),
            $request->file('evidencias') ?? []
        );
        return response()->json($result);
    }

    public function get_historial_recepciones_entrega(Request $request): JsonResponse
    {
        $id_entrega = $request->input('id_reabastecimiento_entrega');
        if (!$id_entrega) {
            return response()->json(ApiResponse::error('El id_reabastecimiento_entrega es requerido'), 400);
        }

        $result = EntregasService::get_historial_recepciones_entrega((int) $id_entrega);
        return response()->json($result);
    }
}
