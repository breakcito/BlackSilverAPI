<?php

namespace App\Modules\VentaCarbon\Controller;

use App\Modules\VentaCarbon\Service\VentaCarbonService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VentaCarbonController
{
    /**
     * Lista las ventas de carbon con sus cargas.
     */
    public function get_ventas(Request $request): JsonResponse
    {
        $opts = [
            'filtros' => $request->query('filtros'),
            'id_cliente' => $request->query('id_cliente'),
            'id_cliente_destino' => $request->query('id_cliente_destino'),
            'id_almacen_empresa_salida' => $request->query('id_almacen_empresa_salida'),
            'mes' => $request->query('mes'),
            'anio' => $request->query('anio'),
        ];

        return response()->json(VentaCarbonService::get_ventas($opts));
    }

    /**
     * Obtiene una venta con el detalle de sus cargas.
     */
    public function get_venta(int $id_venta_carbon): JsonResponse
    {
        return response()->json(VentaCarbonService::get_venta($id_venta_carbon));
    }

    /**
     * Registra una venta de carbon. Las cargas son opcionales: pueden
     * registrarse ahora o agregarse despues.
     */
    public function registrar_venta(Request $request): JsonResponse
    {
        $idEmpleado = $this->id_empleado($request);
        if ($idEmpleado === null) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado que registra'), 422);
        }

        $cargas = $this->cargas_from_request($request);
        if ($cargas === null) {
            return response()->json(ApiResponse::error('El arreglo de cargas no tiene un formato valido'), 422);
        }

        $validator = Validator::make([
            'id_empresa' => $request->input('id_empresa'),
            'id_cliente' => $request->input('id_cliente'),
            'id_cliente_destino' => $request->input('id_cliente_destino'),
            'id_almacen_empresa_salida' => $request->input('id_almacen_empresa_salida'),
            'id_almacen_cliente_llegada' => $request->input('id_almacen_cliente_llegada'),
            'porcentaje_igv' => $request->input('porcentaje_igv'),
            'porcentaje_detraccion' => $request->input('porcentaje_detraccion'),
            'cargas' => $cargas,
        ], [
            'id_empresa' => 'required|integer|min:1',
            'id_cliente' => 'required|integer|min:1',
            'id_cliente_destino' => 'required|integer|min:1',
            'id_almacen_empresa_salida' => 'required|integer|min:1',
            'id_almacen_cliente_llegada' => 'required|integer|min:1',
            'porcentaje_igv' => 'nullable|numeric|min:0|max:100',
            'porcentaje_detraccion' => 'nullable|numeric|min:0|max:100',
            'cargas' => 'nullable|array',
            'cargas.*.id_tipo_carbon' => 'required|integer|min:1',
            'cargas.*.cantidad_toneladas' => 'required|numeric|min:0.0001',
            'cargas.*.calidad' => 'required|string|max:64',
            'cargas.*.fecha_hora_salida' => 'required|string|max:30',
        ], [
            'id_empresa.required' => 'La empresa vendedora es requerida',
            'id_cliente.required' => 'El cliente es requerido',
            'id_cliente_destino.required' => 'El cliente de destino es requerido',
            'id_almacen_empresa_salida.required' => 'El almacen de salida es requerido',
            'id_almacen_cliente_llegada.required' => 'El almacen de llegada es requerido',
            'porcentaje_igv.max' => 'El porcentaje de IGV no puede superar el 100%',
            'porcentaje_detraccion.max' => 'El porcentaje de detraccion no puede superar el 100%',
            'cargas.*.id_tipo_carbon.required' => 'El tipo de carbon es obligatorio en cada carga',
            'cargas.*.cantidad_toneladas.required' => 'La cantidad de toneladas es obligatoria en cada carga',
            'cargas.*.calidad.required' => 'La calidad del carbon es obligatoria en cada carga',
            'cargas.*.calidad.max' => 'La calidad del carbon no es valida',
            'cargas.*.fecha_hora_salida.required' => 'La fecha y hora de salida es obligatoria en cada carga',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        $payload = $validator->validated();
        $payload['cargas'] = $cargas;

        return response()->json(
            VentaCarbonService::registrar_venta($payload, $idEmpleado, $this->archivos_por_carga($request))
        );
    }

    /**
     * Añade cargas despachadas a una venta ya registrada.
     */
    public function registrar_cargas(Request $request, int $id_venta_carbon): JsonResponse
    {
        $idEmpleado = $this->id_empleado($request);
        if ($idEmpleado === null) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado'), 422);
        }

        $cargas = $this->cargas_from_request($request);
        if ($cargas === null) {
            return response()->json(ApiResponse::error('El arreglo de cargas no tiene un formato valido'), 422);
        }

        if (empty($cargas)) {
            return response()->json(ApiResponse::error('Debe registrar al menos una carga'), 422);
        }

        $validator = Validator::make(['cargas' => $cargas], [
            'cargas.*.id_tipo_carbon' => 'required|integer|min:1',
            'cargas.*.cantidad_toneladas' => 'required|numeric|min:0.0001',
            'cargas.*.calidad' => 'required|string|max:64',
            'cargas.*.fecha_hora_salida' => 'required|string|max:30',
        ], [
            'cargas.*.id_tipo_carbon.required' => 'El tipo de carbon es obligatorio en cada carga',
            'cargas.*.cantidad_toneladas.required' => 'La cantidad de toneladas es obligatoria en cada carga',
            'cargas.*.calidad.required' => 'La calidad del carbon es obligatoria en cada carga',
            'cargas.*.calidad.max' => 'La calidad del carbon no es valida',
            'cargas.*.fecha_hora_salida.required' => 'La fecha y hora de salida es obligatoria en cada carga',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        return response()->json(
            VentaCarbonService::registrar_cargas(
                $id_venta_carbon,
                $cargas,
                $idEmpleado,
                $this->archivos_por_carga($request)
            )
        );
    }

    /**
     * Stock disponible de carbon en un almacen de salida.
     */
    public function get_stock_disponible(Request $request): JsonResponse
    {
        $idAlmacen = (int) $request->query('id_almacen');

        if ($idAlmacen <= 0) {
            return response()->json(ApiResponse::error('Debe indicar el almacen de salida'), 422);
        }

        return response()->json(
            ApiResponse::success(VentaCarbonService::get_stock_disponible($idAlmacen))
        );
    }

    /**
     * Confirma la llegada de una carga al almacen del cliente destino.
     */
    public function confirmar_llegada(
        Request $request,
        int $id_venta_carbon,
        int $id_carga_venta_carbon
    ): JsonResponse {
        $analisis = $request->input('analisis_granulometria');
        if (is_string($analisis)) {
            $analisis = json_decode($analisis, true);
        }
        if (!is_array($analisis)) {
            $analisis = null;
        }

        $validator = Validator::make([
            'id_tipo_carbon' => $request->input('id_tipo_carbon'),
            'calidad' => $request->input('calidad'),
            'cantidad_toneladas' => $request->input('cantidad_toneladas'),
            'fecha_hora_ingreso' => $request->input('fecha_hora_ingreso'),
            'id_vehiculo_carbon' => $request->input('id_vehiculo_carbon'),
            'segunda_placa' => $request->input('segunda_placa'),
            'porcentaje_ceniza' => $request->input('porcentaje_ceniza'),
            'porcentaje_humedad' => $request->input('porcentaje_humedad'),
            'porcentaje_azufre' => $request->input('porcentaje_azufre'),
            'porcentaje_pizarra' => $request->input('porcentaje_pizarra'),
            'guia_remitente' => $request->input('guia_remitente'),
            'guia_transportista' => $request->input('guia_transportista'),
            'codigo_ticket_balanza' => $request->input('codigo_ticket_balanza'),
            'observacion' => $request->input('observacion'),
            'fue_aceptado' => $request->boolean('fue_aceptado'),
            'analisis_granulometria' => $analisis,
        ], [
            'id_tipo_carbon' => 'required|integer|min:1',
            'calidad' => 'required|string|max:64',
            'cantidad_toneladas' => 'required|numeric|min:0.0001',
            'fecha_hora_ingreso' => 'required|string|max:30',
            'id_vehiculo_carbon' => 'required|integer|min:1',
            'segunda_placa' => 'nullable|string|max:20',
            'porcentaje_ceniza' => 'nullable|numeric|min:0|max:100',
            'porcentaje_humedad' => 'nullable|numeric|min:0|max:100',
            'porcentaje_azufre' => 'nullable|numeric|min:0|max:100',
            'porcentaje_pizarra' => 'nullable|numeric|min:0|max:100',
            'guia_remitente' => 'nullable|string|max:128',
            'guia_transportista' => 'nullable|string|max:128',
            'codigo_ticket_balanza' => 'nullable|string|max:128',
            'observacion' => 'nullable|string|max:512',
            'fue_aceptado' => 'nullable|boolean',
            'analisis_granulometria' => 'nullable|array',
            'analisis_granulometria.mayor_a_uno' => 'nullable|numeric|min:0|max:100',
            'analisis_granulometria.menor_a_uno' => 'nullable|numeric|min:0|max:100',
            'analisis_granulometria.un_medio' => 'nullable|numeric|min:0|max:100',
        ], [
            'id_tipo_carbon.required' => 'Debe indicar el tipo de carbon confirmado',
            'calidad.required' => 'Debe indicar la calidad del carbon que llego',
            'calidad.max' => 'La calidad del carbon no es valida',
            'cantidad_toneladas.required' => 'Debe indicar la cantidad confirmada',
            'fecha_hora_ingreso.required' => 'Debe indicar la fecha y hora de ingreso',
            'id_vehiculo_carbon.required' => 'Debe indicar el vehiculo que transporto la carga',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        $payload = $validator->validated();

        $archivos = $request->file('evidencias', []);
        if (!is_array($archivos)) {
            $archivos = [$archivos];
        }
        $archivos = array_values(array_filter($archivos));

        return response()->json(
            VentaCarbonService::confirmar_llegada(
                $id_venta_carbon,
                $id_carga_venta_carbon,
                $payload,
                $archivos
            )
        );
    }

    /**
     * Obtiene el id del empleado autenticado.
     */
    private function id_empleado(Request $request): ?int
    {
        $authUser = $request->attributes->get('auth_user');
        $idEmpleado = (int) ($authUser->id_empleado ?? 0);

        return $idEmpleado > 0 ? $idEmpleado : null;
    }

    /**
     * Extrae el arreglo de cargas del request (JSON string o array).
     * @return array<int, array<string, mixed>>|null
     */
    private function cargas_from_request(Request $request): ?array
    {
        $raw = $request->input('cargas');

        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return null;
            }
            $raw = $decoded;
        }

        if (!is_array($raw)) {
            return null;
        }

        // Se indexa de forma continua para que las evidencias_0, evidencias_1...
        // del request coincidan con la posicion de la carga.
        return array_values($raw);
    }

    /**
     * Agrupa los archivos subidos por carga (evidencias_0, evidencias_1...).
     * @return array<string, array<int, \Illuminate\Http\UploadedFile>>
     */
    private function archivos_por_carga(Request $request): array
    {
        $archivosPorCarga = [];

        foreach ($request->allFiles() as $key => $files) {
            if (!str_starts_with($key, 'evidencias')) {
                continue;
            }

            $archivosPorCarga[$key] = is_array($files) ? array_values($files) : [$files];
        }

        return $archivosPorCarga;
    }
}
