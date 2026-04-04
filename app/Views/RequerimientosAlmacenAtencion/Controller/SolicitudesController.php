<?php

namespace App\Views\RequerimientosAlmacenAtencion\Controller;

use App\Shared\Responses\ApiResponse;
use App\Views\RequerimientosAlmacenAtencion\Service\SolicitudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class SolicitudesController extends Controller
{
    public function __construct(
        private SolicitudService $solicitudService
    ) {}

    /**
     * Registrar una solicitud a logística (reabastecimiento).
     */
    public function registrar_solicitud(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_requerimiento' => 'required|integer',
            'observacion' => 'nullable|string',
            'premura' => 'required|string',
            'fecha_entrega_requerida' => 'required|date',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_requerimiento_almacen_detalle' => 'required|integer',
            'detalles.*.id_producto' => 'required|integer',
            'detalles.*.id_unidad_medida' => 'required|integer',
            'detalles.*.cantidad_solicitada' => 'required|numeric',
            'detalles.*.contenido_por_presentacion' => 'required|numeric',
            'detalles.*.cantidad_solicitada_base' => 'required|numeric',
            'detalles.*.comentario' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $result = $this->solicitudService->registrarSolicitudLogistica(
            (int) $request->id_requerimiento,
            (int) $authUser->id_empleado,
            $request->premura,
            $request->fecha_entrega_requerida,
            $request->detalles,
            $request->observacion
        );

        return response()->json($result);
    }

    /**
     * Obtener el historial de solicitudes asociadas a un requerimiento.
     */
    public function get_historial_solicitudes(Request $request): JsonResponse
    {
        $id = $request->input('id_requerimiento');
        if (!$id) {
            return response()->json(ApiResponse::error('El id_requerimiento es requerido'), 400);
        }

        $result = $this->solicitudService->obtenerHistorialSolicitudes((int) $id);

        return response()->json($result);
    }
}
