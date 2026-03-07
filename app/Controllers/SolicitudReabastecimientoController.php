<?php

namespace App\Controllers;

use App\Services\SolicitudReabastecimientoService;
use App\Shared\Enums\Premura;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;

class SolicitudReabastecimientoController extends Controller
{
    public function __construct(
        private SolicitudReabastecimientoService $solicitudService
    ) {}

    public function get_solicitudes(Request $request): JsonResponse
    {
        $id_almacen_solicitante = $request->query('id_almacen_solicitante');

        $result = $this->solicitudService->get_solicitudes(
            $id_almacen_solicitante
        );

        return response()->json($result);
    }

    public function get_detalles_solicitud(Request $request): JsonResponse
    {
        $id_solicitud_reabastecimiento = $request->query('id_solicitud_reabastecimiento');

        $result = $this->solicitudService->get_detalles_solicitud(
            $id_solicitud_reabastecimiento
        );

        return response()->json($result);
    }

    public function crear_solicitud(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        $validator = Validator::make($request->all(), [
            'id_almacen_solicitante' => 'required|integer',
            'premura' => ['required', new Enum(Premura::class)],
            'observacion' => 'nullable|string',
            'fecha_hora_entrega_requerida' => 'nullable|date',
            //
            'detalles' => 'required|array|min:1',
            //
            'detalles.*.id_producto' => 'required|integer',
            'detalles.*.id_unidad_medida' => 'required|integer',
            'detalles.*.cantidad_solicitada' => 'required|numeric|min:0.01',
            'detalles.*.contenido_por_presentacion' => 'required|numeric|min:0',
            'detalles.*.comentario' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $result = $this->solicitudService->crear_solicitud(
            id_almacen_solicitante: $authUser->id_empleado,
            id_empleado_solicitante: $authUser,
            premura: $request->premura,
            observacion: $request->observacion,
            fecha_hora_entrega_requerida: $request->fecha_hora_entrega_requerida,
            detalles: $request->detalles
        );

        return response()->json($result);
    }
}
