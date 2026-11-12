<?php

namespace App\Modules\VentaMineral\Controller;

use App\Modules\VentaMineral\Service\VentaMineralAnalisisLiquidacionService;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class VentaMineralAnalisisLiquidacionController extends Controller
{
    private const CARPETA_EVIDENCIAS_ANALISIS = 'evidencias-analisis-despacho-mineral';

    /**
     * Registrar un analisis de laboratorio del despacho.
     */
    public function registrar_analisis(Request $request, int $id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $validator = Validator::make($request->all(), [
            'id_laboratorio'      => 'required|integer',
            'id_cliente'          => 'nullable|integer',
            'asumido_por'         => 'required|string|max:20',
            'codigo_laboratorio'  => 'nullable|string|max:100',
            'codigo_cliente'      => 'nullable|string|max:100',
            'fecha_analisis'      => 'nullable|date',
            'porcentaje_humedad'  => 'nullable|numeric|min:0|max:100',
            'ley_oro'             => 'nullable|numeric|min:0',
            'ley_plata'           => 'nullable|numeric|min:0',
            'ley_cobre'           => 'nullable|numeric|min:0',
            'ley_zinc'            => 'nullable|numeric|min:0',
            'ley_arsenico'        => 'nullable|numeric|min:0',
            'ley_hierro'          => 'nullable|numeric|min:0',
            'ley_plomo'           => 'nullable|numeric|min:0',
            'costo_servicio'      => 'nullable|numeric|min:0',
        ], [
            'id_laboratorio.required' => 'Debe seleccionar el laboratorio',
            'asumido_por.required'    => 'Debe indicar si el analisis lo asume la empresa o el cliente',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $payload = [
            'id_laboratorio'     => (int) $request->input('id_laboratorio'),
            'id_cliente'         => $request->input('id_cliente'),
            'asumido_por'        => (string) $request->input('asumido_por'),
            'codigo_laboratorio' => $request->input('codigo_laboratorio'),
            'codigo_cliente'     => $request->input('codigo_cliente'),
            'fecha_analisis'     => $request->input('fecha_analisis'),
            'porcentaje_humedad' => (float) ($request->input('porcentaje_humedad') ?? 0),
            'ley_oro'            => (float) ($request->input('ley_oro') ?? 0),
            'ley_plata'          => (float) ($request->input('ley_plata') ?? 0),
            'ley_cobre'          => (float) ($request->input('ley_cobre') ?? 0),
            'ley_zinc'           => (float) ($request->input('ley_zinc') ?? 0),
            'ley_arsenico'       => (float) ($request->input('ley_arsenico') ?? 0),
            'ley_hierro'         => (float) ($request->input('ley_hierro') ?? 0),
            'ley_plomo'          => (float) ($request->input('ley_plomo') ?? 0),
            'costo_servicio'     => (float) ($request->input('costo_servicio') ?? 0),
        ];

        if ($request->hasFile('evidencias')) {
            $payload['evidencias'] = ArchivoHelper::guardarArchivos(
                self::CARPETA_EVIDENCIAS_ANALISIS,
                (array) $request->file('evidencias')
            );
        }

        $result = VentaMineralAnalisisLiquidacionService::registrar_analisis(
            $id,
            $payload,
            (int) $authUser->id_empleado
        );

        return response()->json($result);
    }

    /**
     * Editar un analisis de laboratorio.
     */
    public function actualizar_analisis(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_laboratorio'     => 'required|integer',
            'id_cliente'         => 'nullable|integer',
            'asumido_por'        => 'required|string|max:20',
            'codigo_laboratorio' => 'nullable|string|max:100',
            'codigo_cliente'     => 'nullable|string|max:100',
            'fecha_analisis'     => 'nullable|date',
            'porcentaje_humedad' => 'nullable|numeric|min:0|max:100',
            'ley_oro'            => 'nullable|numeric|min:0',
            'ley_plata'          => 'nullable|numeric|min:0',
            'ley_cobre'          => 'nullable|numeric|min:0',
            'ley_zinc'           => 'nullable|numeric|min:0',
            'ley_arsenico'       => 'nullable|numeric|min:0',
            'ley_hierro'         => 'nullable|numeric|min:0',
            'ley_plomo'          => 'nullable|numeric|min:0',
            'costo_servicio'     => 'nullable|numeric|min:0',
        ], [
            'id_laboratorio.required' => 'Debe seleccionar el laboratorio',
            'asumido_por.required'    => 'Debe indicar si el analisis lo asume la empresa o el cliente',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $payload = [
            'id_laboratorio'     => (int) $request->input('id_laboratorio'),
            'id_cliente'         => $request->input('id_cliente'),
            'asumido_por'        => (string) $request->input('asumido_por'),
            'codigo_laboratorio' => $request->input('codigo_laboratorio'),
            'codigo_cliente'     => $request->input('codigo_cliente'),
            'fecha_analisis'     => $request->input('fecha_analisis'),
            'porcentaje_humedad' => (float) ($request->input('porcentaje_humedad') ?? 0),
            'ley_oro'            => (float) ($request->input('ley_oro') ?? 0),
            'ley_plata'          => (float) ($request->input('ley_plata') ?? 0),
            'ley_cobre'          => (float) ($request->input('ley_cobre') ?? 0),
            'ley_zinc'           => (float) ($request->input('ley_zinc') ?? 0),
            'ley_arsenico'       => (float) ($request->input('ley_arsenico') ?? 0),
            'ley_hierro'         => (float) ($request->input('ley_hierro') ?? 0),
            'ley_plomo'          => (float) ($request->input('ley_plomo') ?? 0),
            'costo_servicio'     => (float) ($request->input('costo_servicio') ?? 0),
        ];

        if ($request->hasFile('evidencias')) {
            $payload['evidencias'] = ArchivoHelper::guardarArchivos(
                self::CARPETA_EVIDENCIAS_ANALISIS,
                (array) $request->file('evidencias')
            );
        }

        $result = VentaMineralAnalisisLiquidacionService::actualizar_analisis($id, $payload);

        return response()->json($result);
    }

    /**
     * Eliminar un analisis de laboratorio.
     */
    public function eliminar_analisis(int $id): JsonResponse
    {
        $result = VentaMineralAnalisisLiquidacionService::eliminar_analisis($id);

        return response()->json($result);
    }

    /**
     * Registrar una liquidacion de mineral.
     */
    public function registrar_liquidacion(Request $request, int $id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $validator = Validator::make($request->all(), [
            'id_cliente'              => 'required|integer',
            'codigo_liquidacion'      => 'required|string|max:100',
            'elemento_quimico'        => 'required|string|max:20',
            'fecha_liquidacion'       => 'required|date',
            'peso_humedo'             => 'required|numeric|min:0.000001',
            'tara'                    => 'nullable|numeric|min:0',
            'porcentaje_humedad'      => 'nullable|numeric|min:0|max:100',
            'merma'                   => 'nullable|numeric|min:0',
            'ley_elemento'            => 'nullable|numeric|min:0',
            'porcentaje_recuperacion' => 'nullable|numeric|min:0|max:100',
            'total_deducciones'        => 'nullable|numeric|min:0',
            'valor_por_tonelada'      => 'required|numeric|min:0.000001',
            'porcentaje_igv'          => 'nullable|numeric|min:0|max:100',
            'porcentaje_detraccion'   => 'nullable|numeric|min:0|max:100',
            'observaciones'           => 'nullable|string|max:500',
        ], [
            'id_cliente.required'         => 'Debe seleccionar el cliente de la liquidacion',
            'codigo_liquidacion.required' => 'Debe indicar el codigo de la liquidacion',
            'elemento_quimico.required'   => 'Debe indicar el elemento quimico',
            'fecha_liquidacion.required'  => 'Debe indicar la fecha de la liquidacion',
            'peso_humedo.required'        => 'Debe indicar el peso humedo',
            'valor_por_tonelada.required' => 'Debe indicar el valor por tonelada',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $result = VentaMineralAnalisisLiquidacionService::registrar_liquidacion(
            $id,
            [
                'id_cliente'              => (int) $request->input('id_cliente'),
                'codigo_liquidacion'      => (string) $request->input('codigo_liquidacion'),
                'elemento_quimico'        => (string) $request->input('elemento_quimico'),
                'fecha_liquidacion'       => (string) $request->input('fecha_liquidacion'),
                'peso_humedo'             => (float) $request->input('peso_humedo'),
                'tara'                    => (float) ($request->input('tara') ?? 0),
                'porcentaje_humedad'      => (float) ($request->input('porcentaje_humedad') ?? 0),
                'merma'                   => (float) ($request->input('merma') ?? 0),
                'ley_elemento'            => (float) ($request->input('ley_elemento') ?? 0),
                'porcentaje_recuperacion' => (float) ($request->input('porcentaje_recuperacion') ?? 0),
                'total_deducciones'        => (float) ($request->input('total_deducciones') ?? 0),
                'valor_por_tonelada'      => (float) $request->input('valor_por_tonelada'),
                'porcentaje_igv'          => $request->input('porcentaje_igv'),
                'porcentaje_detraccion'   => $request->input('porcentaje_detraccion'),
                'observaciones'           => $request->input('observaciones'),
            ],
            (int) $authUser->id_empleado
        );

        return response()->json($result);
    }

    /**
     * Editar una liquidacion de mineral.
     */
    public function actualizar_liquidacion(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_cliente'              => 'required|integer',
            'codigo_liquidacion'      => 'required|string|max:100',
            'elemento_quimico'        => 'required|string|max:20',
            'fecha_liquidacion'       => 'required|date',
            'peso_humedo'             => 'required|numeric|min:0.000001',
            'tara'                    => 'nullable|numeric|min:0',
            'porcentaje_humedad'      => 'nullable|numeric|min:0|max:100',
            'merma'                   => 'nullable|numeric|min:0',
            'ley_elemento'            => 'nullable|numeric|min:0',
            'porcentaje_recuperacion' => 'nullable|numeric|min:0|max:100',
            'total_deducciones'        => 'nullable|numeric|min:0',
            'valor_por_tonelada'      => 'required|numeric|min:0.000001',
            'porcentaje_igv'          => 'nullable|numeric|min:0|max:100',
            'porcentaje_detraccion'   => 'nullable|numeric|min:0|max:100',
            'observaciones'           => 'nullable|string|max:500',
        ], [
            'id_cliente.required'         => 'Debe seleccionar el cliente de la liquidacion',
            'codigo_liquidacion.required' => 'Debe indicar el codigo de la liquidacion',
            'elemento_quimico.required'   => 'Debe indicar el elemento quimico',
            'fecha_liquidacion.required'  => 'Debe indicar la fecha de la liquidacion',
            'peso_humedo.required'        => 'Debe indicar el peso humedo',
            'valor_por_tonelada.required' => 'Debe indicar el valor por tonelada',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $result = VentaMineralAnalisisLiquidacionService::actualizar_liquidacion($id, [
            'id_cliente'              => (int) $request->input('id_cliente'),
            'codigo_liquidacion'      => (string) $request->input('codigo_liquidacion'),
            'elemento_quimico'        => (string) $request->input('elemento_quimico'),
            'fecha_liquidacion'       => (string) $request->input('fecha_liquidacion'),
            'peso_humedo'             => (float) $request->input('peso_humedo'),
            'tara'                    => (float) ($request->input('tara') ?? 0),
            'porcentaje_humedad'      => (float) ($request->input('porcentaje_humedad') ?? 0),
            'merma'                   => (float) ($request->input('merma') ?? 0),
            'ley_elemento'            => (float) ($request->input('ley_elemento') ?? 0),
            'porcentaje_recuperacion' => (float) ($request->input('porcentaje_recuperacion') ?? 0),
            'total_deducciones'        => (float) ($request->input('total_deducciones') ?? 0),
            'valor_por_tonelada'      => (float) $request->input('valor_por_tonelada'),
            'porcentaje_igv'          => $request->input('porcentaje_igv'),
            'porcentaje_detraccion'   => $request->input('porcentaje_detraccion'),
            'observaciones'           => $request->input('observaciones'),
        ]);

        return response()->json($result);
    }

    /**
     * Eliminar una liquidacion de mineral.
     */
    public function eliminar_liquidacion(int $id): JsonResponse
    {
        $result = VentaMineralAnalisisLiquidacionService::eliminar_liquidacion($id);

        return response()->json($result);
    }
}
