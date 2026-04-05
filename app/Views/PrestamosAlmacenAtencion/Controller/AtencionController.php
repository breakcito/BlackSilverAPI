<?php

namespace App\Views\PrestamosAlmacenAtencion\Controller;

use App\Shared\Responses\ApiResponse;
use App\Views\PrestamosAlmacenAtencion\Service\AtencionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class AtencionController extends Controller
{
    /**
     * Obtener listado de préstamos por almacén y periodo
     */
    public function get_prestamos(Request $request): JsonResponse
    {
        $id_almacen = (int) $request->query('id_almacen');
        $mes        = (string) $request->query('mes');
        $yearcito   = (string) $request->query('yearcito');

        if (!$id_almacen || !$mes || !$yearcito) {
            return response()->json(ApiResponse::error('id_almacen, mes y yearcito son requeridos'), 400);
        }

        $result = AtencionService::get_prestamos($id_almacen, $mes, $yearcito);
        return response()->json($result);
    }

    /**
     * Obtener detalles de un préstamo específico
     */
    public function get_detalles_prestamo(Request $request): JsonResponse
    {
        $id_prestamo = (int) $request->query('id_prestamo');
        if (!$id_prestamo) {
            return response()->json(ApiResponse::error('id_prestamo es requerido'), 400);
        }

        $result = AtencionService::get_detalles_prestamo($id_prestamo);
        return response()->json($result);
    }

    /**
     * Obtener historial de entregas de un préstamo
     */
    public function get_historial_entregas(Request $request): JsonResponse
    {
        $id_prestamo = (int) $request->query('id_prestamo');
        if (!$id_prestamo) {
            return response()->json(ApiResponse::error('id_prestamo es requerido'), 400);
        }

        $result = AtencionService::get_historial_entregas($id_prestamo);
        return response()->json($result);
    }

    /**
     * Cambiar estado de uno o varios ítems (Aprobado/Rechazado)
     */
    public function cambiar_estado_detalle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_prestamo_detalle' => 'nullable|integer',
            'ids_detalles'        => 'nullable|array',
            'ids_detalles.*'      => 'integer',
            'nuevo_estado'        => 'required|string',
            'comentario'          => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        // Normalizar IDs
        $ids = [];
        if ($request->has('id_prestamo_detalle')) {
            $ids[] = (int) $request->input('id_prestamo_detalle');
        }
        if ($request->has('ids_detalles')) {
            $ids = array_merge($ids, $request->input('ids_detalles'));
        }
        $ids = array_unique($ids);

        if (empty($ids)) {
            return response()->json(ApiResponse::error('Debe proporcionar al menos un ID de detalle'), 400);
        }

        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $result = AtencionService::cambiar_estado_detalle(
            $ids,
            (int) $authUser->id_empleado,
            (string) $request->input('nuevo_estado'),
            (string) $request->input('comentario')
        );

        return response()->json($result);
    }

    /**
     * Obtener trazabilidad de un ítem
     */
    public function get_trazabilidad_detalle(Request $request): JsonResponse
    {
        $id_detalle = (int) $request->query('id_prestamo_detalle');
        if (!$id_detalle) {
            return response()->json(ApiResponse::error('id_prestamo_detalle es requerido'), 400);
        }

        $result = AtencionService::get_trazabilidad($id_detalle);
        return response()->json($result);
    }
}
