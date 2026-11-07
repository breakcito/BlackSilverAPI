<?php

namespace App\Modules\VentaMineral\Controller;

use App\Modules\VentaMineral\Service\VentaMineralService;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class VentaMineralController extends Controller
{
    private const CARPETA_EVIDENCIAS_FLETE = 'evidencias-flete-despacho-mineral';

    /**
     * Listar despachos de mineral.
     */
    public function get_despachos(Request $request): JsonResponse
    {
        $mes = $request->query('mes') ? (int) $request->query('mes') : null;
        $anio = $request->query('anio') ? (int) $request->query('anio') : null;
        $estado = $request->query('estado') ? (string) $request->query('estado') : null;
        $idLote = $request->query('id_lote_mineral') ? (int) $request->query('id_lote_mineral') : null;

        $result = VentaMineralService::get_despachos($mes, $anio, $estado, $idLote);

        return response()->json($result);
    }

    /**
     * Detalle de un despacho con todo su arbol.
     */
    public function get_despacho(int $id): JsonResponse
    {
        $result = VentaMineralService::get_despacho($id);

        return response()->json($result);
    }

    /**
     * Lotes finalizados todavia sin despacho.
     */
    public function get_lotes_despachables(): JsonResponse
    {
        $result = VentaMineralService::get_lotes_despachables();

        return response()->json($result);
    }

    /**
     * Registrar un despacho de mineral a molienda CON su flete y los gastos
     * iniciales.
     *
     * El flete viaja en el mismo request (`flete[...]`) porque el mineral
     * del despacho se lleva en una sola unidad: la carga no existe sin
     * ella. Los gastos son opcionales (`gastos[0][...]`) y tambien pueden
     * agregarse despues.
     */
    public function registrar_despacho(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $validator = Validator::make($request->all(), [
            'id_lote_mineral' => 'required|integer',
            'id_molino'       => 'required|integer',
            'fecha_despacho'  => 'required|date',
            'cantidad_sacos'  => 'required|numeric|min:0.000001',
            'observaciones'   => 'nullable|string|max:500',
            'flete'           => 'required|array',
            'flete.id_transportista_mineral' => 'required|integer',
            'flete.peso_guia' => 'required|numeric|min:0.000001',
            'gastos'          => 'nullable|array',
            'gastos.*.tipo_gasto' => 'required|string|max:50',
            'gastos.*.monto'  => 'required|numeric|min:0',
        ], [
            'id_lote_mineral.required' => 'Debe seleccionar el lote de mineral a despachar',
            'id_molino.required'       => 'Debe seleccionar el molino de destino',
            'fecha_despacho.required'  => 'Debe indicar la fecha del despacho',
            'cantidad_sacos.required'  => 'Debe indicar la cantidad de sacos',
            'flete.required'           => 'Debe registrar el flete que lleva la carga',
            'flete.id_transportista_mineral.required' => 'Debe seleccionar el transportista del flete',
            'flete.peso_guia.required' => 'Debe indicar el peso de la guia',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $flete = (array) $request->input('flete', []);

        if ($request->hasFile('evidencias')) {
            $flete['evidencias'] = ArchivoHelper::guardarArchivos(
                self::CARPETA_EVIDENCIAS_FLETE,
                (array) $request->file('evidencias')
            );
        }

        $result = VentaMineralService::registrar_despacho([
            'id_lote_mineral' => (int) $request->input('id_lote_mineral'),
            'id_molino'       => (int) $request->input('id_molino'),
            'fecha_despacho'  => (string) $request->input('fecha_despacho'),
            'cantidad_sacos'  => (float) $request->input('cantidad_sacos'),
            'observaciones'   => $request->input('observaciones'),
            'flete'           => $flete,
            'gastos'          => (array) ($request->input('gastos') ?? []),
        ], (int) $authUser->id_empleado);

        return response()->json($result);
    }

    /**
     * Editar un despacho de mineral.
     */
    public function actualizar_despacho(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_molino'      => 'required|integer',
            'fecha_despacho' => 'required|date',
            'cantidad_sacos' => 'required|numeric|min:0.000001',
            'observaciones'  => 'nullable|string|max:500',
        ], [
            'id_molino.required'      => 'Debe seleccionar el molino de destino',
            'fecha_despacho.required' => 'Debe indicar la fecha del despacho',
            'cantidad_sacos.required' => 'Debe indicar la cantidad de sacos',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $result = VentaMineralService::actualizar_despacho($id, [
            'id_molino'      => (int) $request->input('id_molino'),
            'fecha_despacho' => (string) $request->input('fecha_despacho'),
            'cantidad_sacos' => (float) $request->input('cantidad_sacos'),
            'observaciones'  => $request->input('observaciones'),
        ], (int) $request->attributes->get('auth_user')->id_empleado);

        return response()->json($result);
    }

    /**
     * Eliminar un despacho con todo su arbol.
     */
    public function eliminar_despacho(int $id): JsonResponse
    {
        $result = VentaMineralService::eliminar_despacho($id);

        return response()->json($result);
    }
}
