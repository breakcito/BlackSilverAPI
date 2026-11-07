<?php

namespace App\Modules\VentaMineral\Controller;

use App\Modules\VentaMineral\Service\VentaMineralComprobanteService;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class VentaMineralComprobanteController extends Controller
{
    private const CARPETA_EVIDENCIAS_COMPROBANTE = 'evidencias-comprobante-despacho-mineral';
    private const CARPETA_EVIDENCIAS_PAGO = 'evidencias-pago-despacho-mineral';

    /**
     * Registrar el comprobante de flete del despacho.
     */
    public function registrar_comprobante_flete(Request $request, int $id): JsonResponse
    {
        return $this->registrar($request, $id, 'flete');
    }

    /**
     * Registrar el comprobante de molienda del despacho.
     */
    public function registrar_comprobante_molienda(Request $request, int $id): JsonResponse
    {
        return $this->registrar($request, $id, 'molienda');
    }

    /**
     * Registrar el comprobante de analisis del despacho.
     */
    public function registrar_comprobante_analisis(Request $request, int $id): JsonResponse
    {
        return $this->registrar($request, $id, 'analisis');
    }

    /**
     * Registrar el comprobante de liquidacion del despacho.
     */
    public function registrar_comprobante_liquidacion(Request $request, int $id): JsonResponse
    {
        return $this->registrar($request, $id, 'liquidacion');
    }

    /**
     * Editar el comprobante de flete.
     */
    public function actualizar_comprobante_flete(Request $request, int $id): JsonResponse
    {
        return $this->actualizar($request, $id, 'flete');
    }

    /**
     * Editar el comprobante de molienda.
     */
    public function actualizar_comprobante_molienda(Request $request, int $id): JsonResponse
    {
        return $this->actualizar($request, $id, 'molienda');
    }

    /**
     * Editar el comprobante de analisis.
     */
    public function actualizar_comprobante_analisis(Request $request, int $id): JsonResponse
    {
        return $this->actualizar($request, $id, 'analisis');
    }

    /**
     * Editar el comprobante de liquidacion.
     */
    public function actualizar_comprobante_liquidacion(Request $request, int $id): JsonResponse
    {
        return $this->actualizar($request, $id, 'liquidacion');
    }

    /**
     * Registrar un pago sobre el comprobante de flete.
     */
    public function registrar_pago_flete(Request $request, int $id): JsonResponse
    {
        return $this->registrarPago($request, $id, 'flete');
    }

    /**
     * Registrar un pago sobre el comprobante de molienda.
     */
    public function registrar_pago_molienda(Request $request, int $id): JsonResponse
    {
        return $this->registrarPago($request, $id, 'molienda');
    }

    /**
     * Registrar un pago sobre el comprobante de analisis.
     */
    public function registrar_pago_analisis(Request $request, int $id): JsonResponse
    {
        return $this->registrarPago($request, $id, 'analisis');
    }

    /**
     * Registrar un pago sobre el comprobante de liquidacion.
     */
    public function registrar_pago_liquidacion(Request $request, int $id): JsonResponse
    {
        return $this->registrarPago($request, $id, 'liquidacion');
    }

    /**
     * Eliminar el comprobante de flete.
     */
    public function eliminar_comprobante_flete(int $id): JsonResponse
    {
        return response()->json(VentaMineralComprobanteService::eliminar_comprobante('flete', $id));
    }

    /**
     * Eliminar el comprobante de molienda.
     */
    public function eliminar_comprobante_molienda(int $id): JsonResponse
    {
        return response()->json(VentaMineralComprobanteService::eliminar_comprobante('molienda', $id));
    }

    /**
     * Eliminar el comprobante de analisis.
     */
    public function eliminar_comprobante_analisis(int $id): JsonResponse
    {
        return response()->json(VentaMineralComprobanteService::eliminar_comprobante('analisis', $id));
    }

    /**
     * Eliminar el comprobante de liquidacion.
     */
    public function eliminar_comprobante_liquidacion(int $id): JsonResponse
    {
        return response()->json(VentaMineralComprobanteService::eliminar_comprobante('liquidacion', $id));
    }

    /**
     * Registro de un comprobante de cualquier tipo.
     */
    private function registrar(Request $request, int $id_despacho_mineral, string $tipo): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $validator = Validator::make($request->all(), [
            'codigo_comprobante'     => 'required|string|max:100',
            'fecha_emision'          => 'required|date',
            'con_detraccion'         => 'nullable|boolean',
            'porcentaje_detraccion'  => 'nullable|numeric|min:0|max:100',
            'porcentaje_igv'         => 'nullable|numeric|min:0|max:100',
            'id_transportista_mineral' => 'nullable|integer',
            'toneladas_cobradas'     => 'nullable|numeric|min:0',
            'precio_por_tonelada'    => 'nullable|numeric|min:0',
            'monto'                  => 'nullable|numeric|min:0',
            'id_liquidacion_mineral' => 'nullable|integer',
            'id_fletes'              => 'nullable|array',
            'id_fletes.*'            => 'integer',
            'id_analisis'            => 'nullable|array',
            'id_analisis.*'          => 'integer',
        ], [
            'codigo_comprobante.required' => 'Debe indicar el codigo del comprobante',
            'fecha_emision.required'      => 'Debe indicar la fecha de emision',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $payload = $this->build_payload($request);

        $result = VentaMineralComprobanteService::registrar_comprobante(
            $tipo,
            $id_despacho_mineral,
            $payload,
            (int) $authUser->id_empleado
        );

        return response()->json($result);
    }

    /**
     * Edicion de un comprobante de cualquier tipo.
     */
    private function actualizar(Request $request, int $id_comprobante, string $tipo): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'codigo_comprobante'     => 'required|string|max:100',
            'fecha_emision'          => 'required|date',
            'con_detraccion'         => 'nullable|boolean',
            'porcentaje_detraccion'  => 'nullable|numeric|min:0|max:100',
            'porcentaje_igv'         => 'nullable|numeric|min:0|max:100',
            'id_transportista_mineral' => 'nullable|integer',
            'toneladas_cobradas'     => 'nullable|numeric|min:0',
            'precio_por_tonelada'    => 'nullable|numeric|min:0',
            'monto'                  => 'nullable|numeric|min:0',
            'id_liquidacion_mineral' => 'nullable|integer',
            'id_fletes'              => 'nullable|array',
            'id_fletes.*'            => 'integer',
            'id_analisis'            => 'nullable|array',
            'id_analisis.*'          => 'integer',
        ], [
            'codigo_comprobante.required' => 'Debe indicar el codigo del comprobante',
            'fecha_emision.required'      => 'Debe indicar la fecha de emision',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $payload = $this->build_payload($request);

        $result = VentaMineralComprobanteService::actualizar_comprobante(
            $tipo,
            $id_comprobante,
            $payload,
            (int) $request->attributes->get('auth_user')->id_empleado
        );

        return response()->json($result);
    }

    /**
     * Registro de un pago sobre un comprobante.
     */
    private function registrarPago(Request $request, int $id_comprobante, string $tipo): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $validator = Validator::make($request->all(), [
            'medio_pago'                 => 'required|string|max:50',
            'numero_operacion'           => 'nullable|string|max:100',
            'fecha_hora_pago'            => 'required|date',
            'es_para_detraccion'         => 'nullable|boolean',
            'monto_pagado'               => 'required|numeric|min:0.000001',
            'id_cuenta_bancaria_empresa' => 'nullable|integer',
            'observacion'                => 'nullable|string|max:500',
        ], [
            'medio_pago.required'      => 'Debe indicar el medio de pago',
            'fecha_hora_pago.required' => 'Debe indicar la fecha y hora del pago',
            'monto_pagado.required'    => 'Debe indicar el monto pagado',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $payload = [
            'medio_pago'                 => (string) $request->input('medio_pago'),
            'numero_operacion'           => $request->input('numero_operacion'),
            'fecha_hora_pago'            => (string) $request->input('fecha_hora_pago'),
            'es_para_detraccion'         => $request->boolean('es_para_detraccion'),
            'monto_pagado'               => (float) $request->input('monto_pagado'),
            'id_cuenta_bancaria_empresa' => $request->input('id_cuenta_bancaria_empresa'),
            'observacion'                => $request->input('observacion'),
        ];

        if ($request->hasFile('evidencias')) {
            $payload['evidencias'] = ArchivoHelper::guardarArchivos(
                self::CARPETA_EVIDENCIAS_PAGO,
                (array) $request->file('evidencias')
            );
        }

        $result = VentaMineralComprobanteService::registrar_pago(
            $tipo,
            $id_comprobante,
            $payload,
            (int) $authUser->id_empleado
        );

        return response()->json($result);
    }

    /**
     * Construye el payload del comprobante desde el request.
     *
     * @return array<string, mixed>
     */
    private function build_payload(Request $request): array
    {
        $payload = [
            'codigo_comprobante'    => (string) $request->input('codigo_comprobante'),
            'fecha_emision'         => (string) $request->input('fecha_emision'),
            'con_detraccion'        => $request->boolean('con_detraccion'),
            'porcentaje_detraccion' => $request->input('porcentaje_detraccion'),
            'porcentaje_igv'        => $request->input('porcentaje_igv'),
            'id_fletes'             => (array) ($request->input('id_fletes') ?? []),
            'id_analisis'           => (array) ($request->input('id_analisis') ?? []),
        ];

        if ($request->has('id_transportista_mineral')) {
            $payload['id_transportista_mineral'] = (int) $request->input('id_transportista_mineral');
        }

        if ($request->has('toneladas_cobradas')) {
            $payload['toneladas_cobradas'] = (float) $request->input('toneladas_cobradas');
        }

        if ($request->has('precio_por_tonelada')) {
            $payload['precio_por_tonelada'] = (float) $request->input('precio_por_tonelada');
        }

        if ($request->has('monto')) {
            $payload['monto'] = (float) $request->input('monto');
        }

        if ($request->has('id_liquidacion_mineral')) {
            $payload['id_liquidacion_mineral'] = (int) $request->input('id_liquidacion_mineral');
        }

        if ($request->hasFile('evidencias')) {
            $payload['evidencias'] = ArchivoHelper::guardarArchivos(
                self::CARPETA_EVIDENCIAS_COMPROBANTE,
                (array) $request->file('evidencias')
            );
        }

        return $payload;
    }
}
