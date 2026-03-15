<?php

namespace App\Views\SolicitudesReabastecimiento\Controller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SolicitudesController extends Controller
{
    public function __construct(
        private SolicitudesService $service
    ) {}

    // Obtener todas la lista de solicitudes en base al almacen solicitante
    // dentro de un periodo de tiempo (mes y año)
    public function get_solicitudes(Request $request): JsonResponse
    {
        $result = $this->service->get_solicitudes(
            $request->id_almacen_solicitante,
            $request->mes,
            $request->yearcito,
        );
        return response()->json($result);
    }
    
    // Registrar una solicitud y sus detalles
    public function crear_solicitud(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        $result = $this->service->crear_solicitud(
            $request->id_almacen_solicitante,
            $authUser->id_empleado,
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
        $result = $this->service->get_detalles_solicitud(
            $request->id_solicitud_reabastecimiento,
        );
        return response()->json($result);
    }


    // Obtener toda la lista de productos junto a la abreviatura de su unidad de medida
    public function get_productos(Request $request): JsonResponse
    {
        $result = $this->service->get_productos();
        return response()->json($result);
    }

    // Obtener la lista de almacenes en las que el empleado
    // solicitante es reesponsable
    public function get_almacenes(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $result = $this->service->get_almacenes(
            $authUser->id_empleado,
        );
        return response()->json($result);
    }

    // Listar unidades de medida.
    public function get_unidades_medida(Request $request): JsonResponse
    {
        $result = $this->service->get_productos();
        return response()->json($result);
    }
}
