<?php

namespace App\Modules\VentaMineral\Controller;

use App\Modules\VentaMineral\Service\VentaMineralGastoFleteService;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class VentaMineralGastoFleteController extends Controller
{
    private const CARPETA_EVIDENCIAS_FLETE = 'evidencias-flete-despacho-mineral';

    /**
     * Registrar un gasto del despacho.
     */
    public function registrar_gasto(Request $request, int $id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $validator = Validator::make($request->all(), [
            'tipo_gasto'   => 'required|string|max:50',
            'descripcion'  => 'nullable|string|max:500',
            'monto'        => 'required|numeric|min:0',
        ], [
            'tipo_gasto.required' => 'Debe indicar el tipo de gasto',
            'monto.required'      => 'Debe indicar el monto del gasto',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $result = VentaMineralGastoFleteService::registrar_gasto($id, [
            'tipo_gasto'  => (string) $request->input('tipo_gasto'),
            'descripcion' => $request->input('descripcion'),
            'monto'       => (float) $request->input('monto'),
        ], (int) $authUser->id_empleado);

        return response()->json($result);
    }

    /**
     * Editar un gasto del despacho.
     */
    public function actualizar_gasto(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tipo_gasto'  => 'required|string|max:50',
            'descripcion' => 'nullable|string|max:500',
            'monto'       => 'required|numeric|min:0',
        ], [
            'tipo_gasto.required' => 'Debe indicar el tipo de gasto',
            'monto.required'      => 'Debe indicar el monto del gasto',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $result = VentaMineralGastoFleteService::actualizar_gasto($id, [
            'tipo_gasto'  => (string) $request->input('tipo_gasto'),
            'descripcion' => $request->input('descripcion'),
            'monto'       => (float) $request->input('monto'),
        ]);

        return response()->json($result);
    }

    /**
     * Eliminar un gasto del despacho.
     */
    public function eliminar_gasto(int $id): JsonResponse
    {
        $result = VentaMineralGastoFleteService::eliminar_gasto($id);

        return response()->json($result);
    }

    /**
     * Registrar un flete del despacho.
     */
    public function registrar_flete(Request $request, int $id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $validator = Validator::make($request->all(), [
            'id_transportista_mineral' => 'required|integer',
            'placa'                    => 'nullable|string|max:50',
            'licencia_conductor'       => 'nullable|string|max:50',
            'guia_remitente'           => 'nullable|string|max:50',
            'peso_guia'                => 'required|numeric|min:0.000001',
            'codigo_ticket_balanza'    => 'nullable|string|max:50',
            'peso_bruto'               => 'nullable|numeric|min:0',
            'tara'                     => 'nullable|numeric|min:0',
            'peso_neto'                => 'nullable|numeric|min:0',
            'fecha_hora_pesaje'        => 'nullable|date',
            'largo'                    => 'nullable|numeric|min:0',
            'ancho'                    => 'nullable|numeric|min:0',
            'alto'                     => 'nullable|numeric|min:0',
        ], [
            'id_transportista_mineral.required' => 'Debe seleccionar el transportista del flete',
            'peso_guia.required'                => 'Debe indicar el peso de la guia',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $evidencias = $request->hasFile('evidencias')
            ? ArchivoHelper::guardarArchivos(self::CARPETA_EVIDENCIAS_FLETE, (array) $request->file('evidencias'))
            : null;

        $result = VentaMineralGastoFleteService::registrar_flete($id, [
            'id_transportista_mineral' => (int) $request->input('id_transportista_mineral'),
            'placa'                    => $request->input('placa'),
            'licencia_conductor'       => $request->input('licencia_conductor'),
            'guia_remitente'           => $request->input('guia_remitente'),
            'peso_guia'                => (float) $request->input('peso_guia'),
            'codigo_ticket_balanza'    => $request->input('codigo_ticket_balanza'),
            'peso_bruto'               => (float) ($request->input('peso_bruto') ?? 0),
            'tara'                     => (float) ($request->input('tara') ?? 0),
            'peso_neto'                => (float) ($request->input('peso_neto') ?? 0),
            'fecha_hora_pesaje'        => $request->input('fecha_hora_pesaje'),
            'largo'                    => (float) ($request->input('largo') ?? 0),
            'ancho'                    => (float) ($request->input('ancho') ?? 0),
            'alto'                     => (float) ($request->input('alto') ?? 0),
            'evidencias'               => $evidencias,
        ], (int) $authUser->id_empleado);

        return response()->json($result);
    }

    /**
     * Registrar el peso de balanza de un flete.
     *
     * Es una operacion aparte del alta: el flete primero se despacha y
     * recien cuando llega al molino y se pesa se conoce el ticket, el
     * bruto, la tara y el neto.
     */
    public function registrar_balanza(Request $request, int $id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $validator = Validator::make($request->all(), [
            'codigo_ticket_balanza' => 'required|string|max:50',
            'segunda_guia_remitente' => 'nullable|string|max:50',
            'peso_bruto'            => 'required|numeric|min:0.000001',
            'tara'                  => 'required|numeric|min:0.000001',
            'fecha_hora_pesaje'     => 'required|date',
        ], [
            'codigo_ticket_balanza.required' => 'Debe indicar el codigo del ticket de balanza',
            'peso_bruto.required'            => 'Debe indicar el peso bruto',
            'tara.required'                  => 'Debe indicar la tara',
            'fecha_hora_pesaje.required'     => 'Debe indicar la fecha y hora del pesaje',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $payload = [
            'codigo_ticket_balanza' => (string) $request->input('codigo_ticket_balanza'),
            'segunda_guia_remitente' => $request->input('segunda_guia_remitente'),
            'peso_bruto'            => (float) $request->input('peso_bruto'),
            'tara'                  => (float) $request->input('tara'),
            'fecha_hora_pesaje'     => (string) $request->input('fecha_hora_pesaje'),
        ];

        if ($request->hasFile('evidencias')) {
            $payload['evidencias'] = ArchivoHelper::guardarArchivos(
                self::CARPETA_EVIDENCIAS_FLETE,
                (array) $request->file('evidencias')
            );
        }

        $result = VentaMineralGastoFleteService::registrar_balanza(
            $id,
            $payload,
            (int) $authUser->id_empleado
        );

        return response()->json($result);
    }

    /**
     * Editar un flete del despacho.
     */
    public function actualizar_flete(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_transportista_mineral' => 'required|integer',
            'placa'                    => 'nullable|string|max:50',
            'licencia_conductor'       => 'nullable|string|max:50',
            'guia_remitente'           => 'nullable|string|max:50',
            'peso_guia'                => 'required|numeric|min:0.000001',
            'largo'                    => 'nullable|numeric|min:0',
            'ancho'                    => 'nullable|numeric|min:0',
            'alto'                     => 'nullable|numeric|min:0',
        ], [
            'id_transportista_mineral.required' => 'Debe seleccionar el transportista del flete',
            'peso_guia.required'                => 'Debe indicar el peso de la guia',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $payload = [
            'id_transportista_mineral' => (int) $request->input('id_transportista_mineral'),
            'placa'                    => $request->input('placa'),
            'licencia_conductor'       => $request->input('licencia_conductor'),
            'guia_remitente'           => $request->input('guia_remitente'),
            'peso_guia'                => (float) $request->input('peso_guia'),
            'largo'                    => (float) ($request->input('largo') ?? 0),
            'ancho'                    => (float) ($request->input('ancho') ?? 0),
            'alto'                     => (float) ($request->input('alto') ?? 0),
        ];

        if ($request->hasFile('evidencias')) {
            $payload['evidencias'] = ArchivoHelper::guardarArchivos(
                self::CARPETA_EVIDENCIAS_FLETE,
                (array) $request->file('evidencias')
            );
        }

        $result = VentaMineralGastoFleteService::actualizar_flete($id, $payload);

        return response()->json($result);
    }

    /**
     * Eliminar un flete del despacho.
     */
    public function eliminar_flete(int $id): JsonResponse
    {
        $result = VentaMineralGastoFleteService::eliminar_flete($id);

        return response()->json($result);
    }
}
