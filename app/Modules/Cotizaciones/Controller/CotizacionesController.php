<?php

namespace App\Modules\Cotizaciones\Controller;

use App\Modules\Cotizaciones\Service\AuxService;
use App\Modules\Cotizaciones\Service\CotizacionesService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CotizacionesController
{
    /**
     * Registrar un nuevo comparativo con sus cotizaciones
     */
    public function registrar_comparativo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'productos' => 'required|array|min:1',
            'cotizaciones' => 'required|array|min:1',
            'cotizaciones.*.empresas_ids' => 'required|array|min:1',
            'cotizaciones.*.empresas_ids.*' => 'integer',
            'cotizaciones.*.detalles' => 'required|array|min:1',
        ], [
            'productos.required' => 'Debe incluir al menos un producto para el comparativo.',
            'cotizaciones.required' => 'Debe incluir al menos una cotización de proveedor.',
            'cotizaciones.*.empresas_ids.required' => 'Debe seleccionar al menos una empresa para cada cotización.',
            'cotizaciones.*.detalles.required' => 'Cada cotización debe incluir al menos un detalle.',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        return response()->json(CotizacionesService::registrar_comparativo(
            productos: $request->input('productos'),
            cotizaciones: $request->input('cotizaciones'),
            id_empleado: $request->user()->id_empleado,
        ));
    }

    /**
     * Actualizar una cotización existente
     */
    public function actualizar_cotizacion(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_proveedor' => 'required|integer',
            'empresas_ids' => 'required|array|min:1',
            'empresas_ids.*' => 'integer',
            'detalles' => 'required|array|min:1',
            'moneda' => 'required|string',
            'metodo_pago' => 'required|string',
            'total_despues_igv' => 'required|numeric',
        ], [
            'id_proveedor.required' => 'El proveedor es obligatorio.',
            'empresas_ids.required' => 'Debe seleccionar al menos una empresa.',
            'detalles.required' => 'La cotización debe tener detalles.',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        return response()->json(CotizacionesService::actualizar_cotizacion(
            id_cotizacion: $id,
            data: $request->all(),
            id_empleado: $request->user()->id_empleado,
        ));
    }

    /**
     * Listar comparativos agrupados con cotizaciones y detalles
     */
    public function get_listado(Request $request): JsonResponse
    {
        $mes = (int) $request->query('mes', now()->month);
        $year = (int) $request->query('year', now()->year);

        return response()->json(CotizacionesService::listar(mes: $mes, year: $year));
    }

    /**
     * Aprobar parcialmente una cotización y generar la Orden de Compra
     */
    public function aprobar_cotizacion_parcial(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_empresa_compradora' => 'required|integer',
            'detalles_aprobados' => 'required|array|min:1',
            'detalles_aprobados.*.id' => 'required|integer',
            'detalles_aprobados.*.precio_confirmado' => 'required|numeric|min:0',
            'tipo_cambio_aplicado' => 'nullable|numeric',
        ], [
            'id_empresa_compradora.required' => 'Debe elegir la empresa compradora para la Orden de Compra.',
            'detalles_aprobados.required' => 'Debe incluir al menos un producto a ser aprobado.',
            'detalles_aprobados.*.precio_confirmado.required' => 'Cada producto aprobado debe tener un precio confirmado.',
            'tipo_cambio_aplicado.numeric' => 'El tipo de cambio aplicado debe ser un número.',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        return response()->json(CotizacionesService::aprobar_cotizacion_parcial(
            id_cotizacion: $id,
            id_empresa_compradora: $request->input('id_empresa_compradora'),
            id_empleado: $request->user()->id_empleado,
            detalles_aprobados: $request->input('detalles_aprobados'),
            tipo_cambio_aplicado: $request->input('tipo_cambio_aplicado') ? (float) $request->input('tipo_cambio_aplicado') : null
        ));
    }
}
