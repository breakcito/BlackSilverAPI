<?php

namespace App\Views\PrestamosAlmacenAtencion;

use App\Shared\Responses\ApiResponse;
use App\Views\PrestamosAlmacenAtencion\PrestamosAtencionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class PrestamosAtencionController extends Controller
{
    // =========================================================================
    // AUXILIARES
    // =========================================================================

    /**
     * Almacenes donde el usuario autenticado es responsable.
     * El front los muestra en el select inicial antes de listar los préstamos.
     */
    public function get_almacenes_autorizados(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $result = PrestamosAtencionService::get_almacenes_autorizados($authUser->id_empleado);
        return response()->json($result);
    }

    /**
     * Lista de empleados activos para seleccionar como entregador o receptor.
     */
    public function get_empleados(): JsonResponse
    {
        $result = PrestamosAtencionService::get_empleados();
        return response()->json($result);
    }

    /**
     * Lotes disponibles de un producto en el almacén prestamista.
     * El front los muestra al momento de armar el despacho.
     */
    public function get_lotes_disponibles(Request $request): JsonResponse
    {
        $id_producto = $request->input('id_producto');
        $id_almacen  = $request->input('id_almacen');

        if (!$id_producto || !$id_almacen) {
            return response()->json(ApiResponse::error('id_producto e id_almacen son requeridos'), 400);
        }

        $result = PrestamosAtencionService::get_lotes_disponibles((int) $id_producto, (int) $id_almacen);
        return response()->json($result);
    }

    // =========================================================================
    // PRÉSTAMOS (listado y detalle para el almacén prestamista)
    // =========================================================================

    /**
     * Listado de préstamos que han llegado al almacén seleccionado.
     * Requiere: id_almacen, mes, yearcito.
     */
    public function get_prestamos(Request $request): JsonResponse
    {
        $id_almacen = $request->input('id_almacen');
        $mes        = $request->input('mes');
        $yearcito   = $request->input('yearcito');

        if (!$id_almacen || !$mes || !$yearcito) {
            return response()->json(ApiResponse::error('id_almacen, mes y yearcito son requeridos'), 400);
        }

        $result = PrestamosAtencionService::get_prestamos_por_almacen((int) $id_almacen, $mes, $yearcito);
        return response()->json($result);
    }

    /**
     * Detalle de un préstamo: ítems + historial de entregas realizadas.
     * El front lo usa en el "Ojito".
     */
    public function get_detalle_prestamo(Request $request): JsonResponse
    {
        $id_prestamo = $request->input('id_prestamo');
        if (!$id_prestamo) {
            return response()->json(ApiResponse::error('id_prestamo es requerido'), 400);
        }

        $result = PrestamosAtencionService::get_prestamo_detalle((int) $id_prestamo);
        return response()->json($result);
    }

    // =========================================================================
    // DESPACHO
    // =========================================================================

    /**
     * Registra el despacho de productos del préstamo desde el almacén prestamista.
     * Deduce stock de los lotes elegidos y genera el Kardex de Salida.
     */
    public function registrar_despacho(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_prestamo'           => 'required|integer',
            'id_empleado_recibe'    => 'required|integer',
            'fecha_hora_entrega'    => 'required|date',
            'observacion'           => 'nullable|string|max:255',
            'detalles'              => 'required|array|min:1',
            'detalles.*.id_prestamo_detalle' => 'required|integer',
            'detalles.*.id_lote_salida'      => 'required|integer',
            'detalles.*.cantidad'            => 'required|numeric|min:0.01',
            'detalles.*.cantidad_base'       => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $result = PrestamosAtencionService::registrar_despacho(
            (int) $request->id_prestamo,
            $authUser->id_empleado,
            (int) $request->id_empleado_recibe,
            $request->fecha_hora_entrega,
            $request->observacion,
            $request->detalles
        );

        return response()->json($result);
    }
}
