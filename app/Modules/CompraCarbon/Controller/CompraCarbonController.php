<?php

namespace App\Modules\CompraCarbon\Controller;

use App\Modules\CompraCarbon\Service\CompraCarbonService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompraCarbonController
{
    public function get_compras(Request $request): JsonResponse
    {
        $opts = [
            'filtros' => $request->query('filtros'),
            'id_empresa' => $request->query('id_empresa'),
            'id_proveedor' => $request->query('id_proveedor'),
            'mes' => $request->query('mes'),
            'anio' => $request->query('anio'),
        ];

        return response()->json(CompraCarbonService::get_compras($opts));
    }

    public function get_compra_con_detalles(int $id_compra_carbon): JsonResponse
    {
        return response()->json(
            CompraCarbonService::get_compra_con_detalles($id_compra_carbon)
        );
    }

    /**
     * Registro preliminar de la compra de carbón.
     */
    public function crear_compra(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_empresa' => 'required|integer|min:1',
            'id_proveedor' => 'required|integer|min:1',
            'fecha_hora_ingreso' => 'nullable|string',
            'detalles' => 'required|array|min:1',
            'detalles.0.id_tipo_carbon' => 'required|integer|min:1',
            'detalles.0.cantidad' => 'required|numeric|min:0.01',
            'detalles.0.precio_unitario' => 'nullable|numeric|min:0',
        ], [
            'id_empresa.required' => 'Empresa requerida',
            'id_proveedor.required' => 'Proveedor requerido',
            'detalles.required' => 'Debe indicar al menos un ítem preliminar',
            'detalles.0.id_tipo_carbon.required' => 'Tipo de carbón requerido',
            'detalles.0.cantidad.required' => 'Cantidad en toneladas requerida',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        $authUser = $request->attributes->get('auth_user');
        $id_empleado_registro = (int) ($authUser->id_empleado ?? 0);
        if ($id_empleado_registro <= 0) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado de registro'), 422);
        }

        return response()->json(
            CompraCarbonService::crear_compra($validator->validated(), $id_empleado_registro)
        );
    }

    /**
     * Confirmación de llegada de la carga (completa cabecera y detalles).
     */
    public function confirmar_compra(Request $request, int $id_compra_carbon): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $id_empleado_confirma = (int) ($authUser->id_empleado ?? 0);
        if ($id_empleado_confirma <= 0) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado de confirmación'), 422);
        }

        $validator = Validator::make($request->all(), [
            'id_empresa' => 'required|integer|min:1',
            'id_proveedor' => 'required|integer|min:1',
            'tipo_despacho' => 'required|in:envio,recojo,Envio,Recojo,Envío',
            'id_almacen_proveedor' => 'nullable|integer|min:1',
            'id_almacen' => 'nullable|integer|min:1',
            'id_almacen_cliente' => 'nullable|integer|min:1',
            'aplica_igv' => 'required|boolean',
            'porcentaje_igv' => 'nullable|numeric|min:0|max:100',
            'fecha_hora_ingreso' => 'required|string',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_tipo_carbon' => 'required|integer|min:1',
            'detalles.*.cantidad' => 'required|numeric|min:0.01',
            'detalles.*.precio_unitario' => 'required|numeric|min:0',
            'detalles.*.pagar_flete' => 'required|boolean',
            'detalles.*.id_transportista' => 'required_if:detalles.*.pagar_flete,true|nullable|integer|min:1',
            'detalles.*.costo_flete_por_tonelada' => 'required_if:detalles.*.pagar_flete,true|nullable|numeric|min:0',
            'evidencias' => 'nullable|array',
        ], [
            'tipo_despacho.required' => 'Tipo de despacho requerido (envío o recojo)',
            'id_almacen_proveedor.required_if' => 'Almacén del proveedor requerido para recojo',
            'fecha_hora_ingreso.required' => 'Fecha y hora de ingreso requeridas',
            'detalles.required' => 'La compra confirmada debe tener al menos una carga',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        return response()->json(
            CompraCarbonService::confirmar_compra($id_compra_carbon, $validator->validated(), $id_empleado_confirma)
        );
    }

    /**
     * Edición de una compra (registra en log_cambios).
     */
    public function actualizar_compra(Request $request, int $id_compra_carbon): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $id_empleado = (int) ($authUser->id_empleado ?? 0);
        $nombre_empleado = trim(($authUser->nombre ?? '') . ' ' . ($authUser->apellido ?? ''));
        if ($id_empleado <= 0) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado editor'), 422);
        }

        $motivo = $request->input('motivo') ? (string) $request->input('motivo') : null;

        return response()->json(
            CompraCarbonService::actualizar_compra(
                $id_compra_carbon,
                $request->all(),
                $id_empleado,
                $nombre_empleado,
                $motivo
            )
        );
    }

    /**
     * Aprobación de la liquidación con anticipos.
     */
    public function aprobar_liquidacion(Request $request, int $id_compra_carbon): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $id_empleado_aprueba = (int) ($authUser->id_empleado ?? 0);
        if ($id_empleado_aprueba <= 0) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado aprobador'), 422);
        }

        $validator = Validator::make($request->all(), [
            'anticipos' => 'nullable|array',
            'anticipos.*.id_anticipo_proveedor' => 'required_with:anticipos|integer|min:1',
            'anticipos.*.monto_retirado' => 'required_with:anticipos|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        /** @var array<int, array{id_anticipo_proveedor: int, monto_retirado: float}> $anticipos */
        $anticipos = $request->input('anticipos', []);

        return response()->json(
            CompraCarbonService::aprobar_liquidacion($id_compra_carbon, $id_empleado_aprueba, $anticipos)
        );
    }

    /**
     * Anulación de compra mientras no esté liquidada.
     */
    public function anular_compra(Request $request, int $id_compra_carbon): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $id_empleado_anula = (int) ($authUser->id_empleado ?? 0);
        if ($id_empleado_anula <= 0) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado que anula'), 422);
        }

        return response()->json(
            CompraCarbonService::anular_compra($id_compra_carbon, $id_empleado_anula)
        );
    }

    /**
     * Reemplazo de evidencias.
     */
    public function set_evidencias(Request $request, int $id_compra_carbon): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'evidencias' => 'present|array',
            'evidencias.*.url' => 'required|string',
            'evidencias.*.path_relativo' => 'required|string',
            'evidencias.*.nombre_original' => 'nullable|string',
            'evidencias.*.extension' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        /** @var array<int, array<string, mixed>> $evidencias */
        $evidencias = $request->input('evidencias', []);

        return response()->json(
            CompraCarbonService::set_evidencias($id_compra_carbon, $evidencias)
        );
    }

    /**
     * Verificación de documentos duplicados (ticket balanza y guías).
     */
    public function verificar_documentos_duplicados(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_proveedor' => 'required|integer|min:1',
            'tickets' => 'nullable|array',
            'tickets.*' => 'string',
            'guias_remitente' => 'nullable|array',
            'guias_remitente.*' => 'string',
            'guias_transportista' => 'nullable|array',
            'guias_transportista.*' => 'string',
            'id_compra_carbon' => 'nullable|integer',
        ], [
            'id_proveedor.required' => 'Proveedor requerido para la verificación',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        return response()->json(
            CompraCarbonService::verificar_documentos_duplicados($validator->validated())
        );
    }
}
