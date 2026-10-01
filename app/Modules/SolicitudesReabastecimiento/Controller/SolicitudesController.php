<?php

namespace App\Modules\SolicitudesReabastecimiento\Controller;

use App\Shared\Enums\_Generic\Premura;
use App\Shared\Responses\ApiResponse;
use App\Modules\SolicitudesReabastecimiento\Service\SolicitudesService;
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
            'es_auditable' => 'required|boolean',
            'fecha_solicitud' => 'nullable|date',
            'fecha_entrega_requerida' => 'nullable|string',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_producto' => 'required|integer',
            'detalles.*.id_unidad_medida' => 'required|integer',
            'detalles.*.cantidad_solicitada' => 'required|numeric|min:0.01',
            'detalles.*.contenido_por_presentacion' => 'required|numeric|min:0.01',
            'detalles.*.comentario' => 'nullable|string',
            // Campos de smart calc: opcionales (modelo clasico no los manda).
            'detalles.*.con_magnitud' => 'nullable|boolean',
            'detalles.*.cantidad_items' => 'nullable|numeric|min:0',
            'detalles.*.valor_magnitud' => 'nullable|numeric|min:0',
            'detalles.*.valor_magnitud_base' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $premura = Premura::from($request->input('premura'));
        $result = SolicitudesService::crear_solicitud(
            id_almacen_solicitante: (int) $request->id_almacen_solicitante,
            id_empleado_solicitante: (int) $authUser->id_empleado,
            premura: $premura,
            es_auditable: (bool) $request->es_auditable,
            detalles: $request->detalles,
            observacion: $request->observacion,
            fecha_entrega_requerida: $request->fecha_entrega_requerida,
            fecha_solicitud: $request->fecha_solicitud,
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

    /**
     * Edita una solicitud existente. Permite modificar la cabecera y los
     * detalles que aun no tengan entregas iniciadas. Tambien permite agregar
     * y eliminar detalles (siempre que los eliminados no tengan entregas).
     */
    public function editar_solicitud(Request $request, int $id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $reglas = [
            'observacion' => 'nullable|string',
            'premura' => 'nullable|string',
            'fecha_solicitud' => 'nullable|date',
            'fecha_entrega_requerida' => 'nullable|string',
            'es_auditable' => 'nullable|boolean',
            'detalles_editar' => 'nullable|array',
            'detalles_editar.*.id_solicitud_reabastecimiento_detalle' => 'required_with:detalles_editar|integer',
            'detalles_editar.*.id_unidad_medida' => 'nullable|integer',
            'detalles_editar.*.cantidad_solicitada' => 'nullable|numeric|min:0',
            'detalles_editar.*.contenido_por_presentacion' => 'nullable|numeric|min:0.0001',
            'detalles_editar.*.comentario' => 'nullable|string',
            'detalles_editar.*.con_magnitud' => 'nullable|boolean',
            'detalles_editar.*.cantidad_items' => 'nullable|numeric|min:0',
            'detalles_editar.*.valor_magnitud' => 'nullable|numeric|min:0',
            'detalles_editar.*.valor_magnitud_base' => 'nullable|numeric|min:0',
            'detalles_eliminar' => 'nullable|array',
            'detalles_eliminar.*' => 'integer',
            'detalles_crear' => 'nullable|array',
            'detalles_crear.*.id_producto' => 'required_with:detalles_crear|integer',
            'detalles_crear.*.id_unidad_medida' => 'required_with:detalles_crear|integer',
            'detalles_crear.*.cantidad_solicitada' => 'required_with:detalles_crear|numeric|min:0.01',
            'detalles_crear.*.contenido_por_presentacion' => 'required_with:detalles_crear|numeric|min:0.0001',
            'detalles_crear.*.comentario' => 'nullable|string',
            'detalles_crear.*.con_magnitud' => 'nullable|boolean',
            'detalles_crear.*.cantidad_items' => 'nullable|numeric|min:0',
            'detalles_crear.*.valor_magnitud' => 'nullable|numeric|min:0',
            'detalles_crear.*.valor_magnitud_base' => 'nullable|numeric|min:0',
        ];

        $validator = Validator::make($request->all(), $reglas);
        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos: ' . implode(', ', $validator->errors()->all())), 400);
        }

        $cabecera = [
            'observacion' => $request->input('observacion'),
            'premura' => $request->input('premura'),
            'fecha_solicitud' => $request->input('fecha_solicitud'),
            'fecha_entrega_requerida' => $request->input('fecha_entrega_requerida'),
            'es_auditable' => $request->has('es_auditable') ? (bool) $request->es_auditable : null,
        ];

        $detalles_editar = $request->input('detalles_editar', []);
        $detalles_crear = $request->input('detalles_crear', []);
        $detalles_eliminar = $request->input('detalles_eliminar', []);

        try {
            $resultado = SolicitudesService::editar_solicitud(
                id_solicitud: $id,
                id_empleado_editor: (int) $authUser->id_empleado,
                cabecera: $cabecera,
                detalles_editar: $detalles_editar,
                detalles_eliminar: $detalles_eliminar,
                detalles_crear: $detalles_crear,
            );

            return response()->json($resultado);
        } catch (\Exception $e) {
            return response()->json(ApiResponse::error('Error al editar solicitud: ' . $e->getMessage()), 500);
        }
    }
}
