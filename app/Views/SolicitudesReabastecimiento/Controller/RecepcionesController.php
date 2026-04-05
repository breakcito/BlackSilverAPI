<?php

namespace App\Views\SolicitudesReabastecimiento\Controller;

use App\Shared\Responses\ApiResponse;
use App\Views\SolicitudesReabastecimiento\Service\RecepcionesService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;

class RecepcionesController extends Controller
{
    /**
     * Registrar una recepción de stock para una entrega específica.
     */
    public function registrar_recepcion(Request $request): JsonResponse
    {
        $data = $request->all();
        $id_empleado_registro = $data['id_empleado_registro'] ?? null;
        $recepcion = $data['recepcion'] ?? null;
        $evidencias = $request->file('evidencias') ?? [];

        // Los datos de la recepción vienen en formato JSON dentro del campo 'recepcion' 
        // si se envía con MultipartForm, o directamente si es JSON.
        if (is_string($recepcion)) {
            $recepcion = json_decode($recepcion, true);
        }

        if (!$id_empleado_registro || !$recepcion || !is_array($recepcion)) {
            return response()->json(ApiResponse::error('Datos incompletos para el registro de la recepción'), 400);
        }

        $result = RecepcionesService::registrar_recepcion(
            (int) $id_empleado_registro,
            $recepcion,
            $evidencias
        );

        return response()->json($result);
    }

    /**
     * Obtener el historial de recepciones de una entrega
     */
    public function get_historial_recepciones_entrega(Request $request): JsonResponse
    {
        $id_reabastecimiento_entrega = $request->input('id_reabastecimiento_entrega');
        $tipo_entrega = $request->input('tipo_entrega', 'Solicitud'); // Solicitud | Prestamo

        if (!$id_reabastecimiento_entrega) {
            return response()->json(ApiResponse::error('El id_reabastecimiento_entrega es requerido'), 400);
        }

        $result = RecepcionesService::obtener_historial_recepciones(
            (int) $id_reabastecimiento_entrega,
            $tipo_entrega
        );

        return response()->json($result);
    }
}
