
<?php

namespace App\Controllers;

use App\Services\PrestamoService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PrestamoController extends Controller
{
    protected PrestamoService $prestamoService;

    public function __construct(PrestamoService $prestamoService)
    {
        $this->prestamoService = $prestamoService;
    }

    public function get_prestamos(Request $request)
    {
        $id_almacen = $request->query('id_almacen');
        if (! $id_almacen) {
            return response()->json(['success' => false, 'message' => 'El id_almacen es requerido'], 400);
        }

        return $this->prestamoService->obtener_prestamos(
            (int) $id_almacen,
            $request->query('estado')
        );
    }

    public function crear_prestamo(Request $request)
    {
        $authUser = $request->attributes->get('auth_user');
        if (! $authUser) {
            return response()->json(['success' => false, 'message' => 'No autorizado'], 401);
        }

        return $this->prestamoService->crear_prestamo(
            (int) $authUser->id_usuario,
            (int) $request->input('id_almacen_solicitante'),
            $request->input('motivo'),
            $request->input('fecha_prestamo'),
            $request->input('detalles')
        );
    }

    public function obtener_por_id(Request $request)
    {
        return $this->prestamoService->obtener_por_id(
            (int) $request->input('id_prestamo')
        );
    }

    public function obtener_trazabilidad_detalle(Request $request)
    {
        return $this->prestamoService->obtener_trazabilidad_detalle(
            (int) $request->input('id_prestamo_detalle')
        );
    }

    public function buscar_stock_global(Request $request)
    {
        return $this->prestamoService->buscar_stock_global(
            (int) $request->input('id_producto'),
            (int) $request->input('id_almacen_excluido')
        );
    }
}
