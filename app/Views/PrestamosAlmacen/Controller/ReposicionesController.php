<?php

namespace App\Views\PrestamosAlmacen\Controller;

use App\Shared\Responses\ApiResponse;
use App\Views\PrestamosAlmacen\Service\ReposicionesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class ReposicionesController extends Controller
{
    /**
     * Obtiene el historial de reposiciones de un préstamo.
     */
    public function get_historial(Request $request): JsonResponse
    {
        $id_prestamo_almacen = (int) $request->query('id_prestamo_almacen');
        if (!$id_prestamo_almacen) {
            return response()->json(ApiResponse::error('El id_prestamo_almacen es requerido'), 400);
        }

        $result = ReposicionesService::get_historial($id_prestamo_almacen);
        return response()->json($result);
    }

    /**
     * Registra una nueva reposición.
     */
    public function registrar_reposicion(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_prestamo_almacen' => 'required|integer',
            'id_almacen_entrega' => 'required|integer',
            'id_empleado_registro' => 'required|integer',
            'fecha_hora_reposicion' => 'required|date',
            'observacion' => 'nullable|string',
            'items' => 'required', // Puede ser string JSON o array
            'evidencias' => 'nullable|array',
            'evidencias.*' => 'file',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        // Decodificamos el JSON de los items si vienen como string (común en Multipart)
        $items = $request->input('items');
        if (is_string($items)) {
            $items = json_decode($items, true);
        }

        // Validación adicional para los items después de decodificar
        if (!is_array($items) || count($items) === 0) {
            return response()->json(ApiResponse::error('Los items son requeridos y deben ser un array válido'), 400);
        }

        $result = ReposicionesService::registrar_reposicion(
            (int) $request->input('id_prestamo_almacen'),
            (int) $request->input('id_almacen_entrega'),
            (int) $request->input('id_empleado_registro'),
            $request->input('fecha_hora_reposicion'),
            $request->input('observacion'),
            $items,
            $request->file('evidencias')
        );

        return response()->json($result);
    }

    /**
     * Obtiene los detalles de una reposición para el proceso de recepción.
     */
    public function get_detalles_recepcion(Request $request): JsonResponse
    {
        $id_reposicion = $request->input('id_reposicion');
        if (!$id_reposicion) {
            return response()->json(ApiResponse::error('El id_reposicion es requerido'), 400);
        }

        $detalles = \App\Views\PrestamosAlmacen\Data\ReposicionesData::get_detalles_entrega_reposicion((int) $id_reposicion);
        return response()->json(ApiResponse::success($detalles));
    }

    /**
     * Registra la recepción masiva de reposiciones.
     */
    public function recibir_reposicion(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'recepciones' => 'required|array|min:1',
            'recepciones.*.id_reabastecimiento_entrega' => 'required|integer',
            'recepciones.*.items' => 'required|array|min:1',
            'recepciones.*.items.*.id_solicitud_reabastecimiento_detalle' => 'required|integer',
            'recepciones.*.items.*.es_nuevo_lote' => 'required|boolean',
            'recepciones.*.items.*.cantidad_base' => 'required|numeric|min:0.01',
            'recepciones.*.items.*.id_lote_existente' => 'nullable|integer',
            'recepciones.*.items.*.fecha_vencimiento' => 'nullable|date',
            'recepciones.*.items.*.id_unidad_medida' => 'nullable|integer',
            'recepciones.*.items.*.contenido_por_presentacion' => 'nullable|numeric|min:0.01',
            'recepciones.*.items.*.fecha_ingreso' => 'nullable|date',
            'recepciones.*.items.*.descripcion' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        // Marcamos el tipo como 'Reposicion' para que el EntregasService sepa cómo procesarlo
        $recepciones = array_map(function ($r) {
            $r['tipo_entrega'] = 'Reposicion';
            return $r;
        }, $request->input('recepciones'));

        $result = \App\Views\SolicitudesReabastecimiento\Service\EntregasService::recibir_entregas_bulk($recepciones);
        return response()->json($result);
    }
}
