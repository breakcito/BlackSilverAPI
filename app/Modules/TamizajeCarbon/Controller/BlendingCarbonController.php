<?php

namespace App\Modules\TamizajeCarbon\Controller;

use App\Modules\TamizajeCarbon\Service\BlendingCarbonService;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BlendingCarbonController
{
    /**
     * Lista los blendings registrados (filtrable por mes, almacen y tipo).
     */
    public function get_blendings(Request $request): JsonResponse
    {
        $opts = [
            'id_almacen' => $request->query('id_almacen'),
            'id_tipo_carbon' => $request->query('id_tipo_carbon'),
            'mes' => $request->query('mes'),
            'anio' => $request->query('anio'),
            'filtros' => $request->query('filtros'),
        ];

        return response()->json(BlendingCarbonService::get_blendings($opts));
    }

    /**
     * Obtiene un blending con el detalle del stock que tomo.
     */
    public function get_blending(int $id_blending_carbon): JsonResponse
    {
        return response()->json(BlendingCarbonService::get_blending($id_blending_carbon));
    }

    /**
     * Registra un blending: toma stock de las calidades indicadas y produce
     * un lote nuevo con su propio stock.
     */
    public function registrar_blending(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $idEmpleado = (int) ($authUser->id_empleado ?? 0);
        if ($idEmpleado <= 0) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado'), 422);
        }

        $detalles = $this->detalles_from_request($request);
        if ($detalles === null) {
            return response()->json(ApiResponse::error('El arreglo de detalles no tiene un formato valido'), 422);
        }

        $validator = Validator::make([
            'id_almacen' => $request->input('id_almacen'),
            'id_empleado_supervisor' => $request->input('id_empleado_supervisor'),
            'id_tipo_carbon' => $request->input('id_tipo_carbon'),
            'porcentaje_ceniza' => $request->input('porcentaje_ceniza'),
            'fecha_hora_blending' => $request->input('fecha_hora_blending'),
            'detalles' => $detalles,
        ], [
            'id_almacen' => 'nullable|integer|min:1',
            'id_empleado_supervisor' => 'nullable|integer|min:1',
            'id_tipo_carbon' => 'required|integer|min:1',
            'porcentaje_ceniza' => 'required|numeric|min:0|max:100',
            'fecha_hora_blending' => 'required|string|max:30',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_stock_carbon' => 'required|integer|min:1',
            'detalles.*.cantidad_tomada' => 'required|numeric|min:0',
        ], [
            'id_tipo_carbon.required' => 'Debe indicar el tipo de carbon del blending',
            'porcentaje_ceniza.required' => 'Debe indicar el porcentaje de ceniza',
            'fecha_hora_blending.required' => 'Debe indicar la fecha y hora del blending',
            'detalles.required' => 'Debe indicar al menos una calidad con cantidad a tomar',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        $payload = $validator->validated();
        $payload['detalles'] = $detalles;
        $payload['evidencias'] = $this->guardar_evidencias($request);

        return response()->json(
            BlendingCarbonService::registrar_blending($payload, $idEmpleado)
        );
    }

    /**
     * Actualiza un blending y recalcula el stock que toma.
     */
    public function actualizar_blending(Request $request, int $id_blending_carbon): JsonResponse
    {
        $detalles = $this->detalles_from_request($request);
        if ($detalles === null) {
            return response()->json(ApiResponse::error('El arreglo de detalles no tiene un formato valido'), 422);
        }

        $validator = Validator::make([
            'id_empleado_supervisor' => $request->input('id_empleado_supervisor'),
            'id_tipo_carbon' => $request->input('id_tipo_carbon'),
            'porcentaje_ceniza' => $request->input('porcentaje_ceniza'),
            'fecha_hora_blending' => $request->input('fecha_hora_blending'),
            'detalles' => $detalles,
        ], [
            'id_empleado_supervisor' => 'nullable|integer|min:1',
            'id_tipo_carbon' => 'required|integer|min:1',
            'porcentaje_ceniza' => 'required|numeric|min:0|max:100',
            'fecha_hora_blending' => 'required|string|max:30',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_stock_carbon' => 'required|integer|min:1',
            'detalles.*.cantidad_tomada' => 'required|numeric|min:0',
        ], [
            'id_tipo_carbon.required' => 'Debe indicar el tipo de carbon del blending',
            'porcentaje_ceniza.required' => 'Debe indicar el porcentaje de ceniza',
            'fecha_hora_blending.required' => 'Debe indicar la fecha y hora del blending',
            'detalles.required' => 'Debe indicar al menos una calidad con cantidad a tomar',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        $payload = $validator->validated();
        $payload['detalles'] = $detalles;

        // Si no se suben archivos nuevos se conservan las evidencias previas.
        $evidencias = $this->guardar_evidencias($request);
        if ($evidencias !== null) {
            $payload['evidencias'] = $evidencias;
        }

        return response()->json(
            BlendingCarbonService::actualizar_blending($id_blending_carbon, $payload)
        );
    }

    /**
     * Agrega mas stock tomado a un blending ya registrado.
     */
    public function agregar_detalles(Request $request, int $id_blending_carbon): JsonResponse
    {
        $detalles = $this->detalles_from_request($request);
        if ($detalles === null) {
            return response()->json(ApiResponse::error('El arreglo de detalles no tiene un formato valido'), 422);
        }

        $validator = Validator::make(['detalles' => $detalles], [
            'detalles' => 'required|array|min:1',
            'detalles.*.id_stock_carbon' => 'required|integer|min:1',
            'detalles.*.cantidad_tomada' => 'required|numeric|min:0',
        ], [
            'detalles.required' => 'Debe indicar al menos una calidad con cantidad a tomar',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        return response()->json(
            BlendingCarbonService::agregar_detalles($id_blending_carbon, [
                'detalles' => $detalles,
            ])
        );
    }

    /**
     * Agrega archivos a las evidencias de un blending ya registrado.
     */
    public function subir_evidencias(Request $request, int $id_blending_carbon): JsonResponse
    {
        $archivos = $request->file('evidencias', []);
        if (!is_array($archivos)) {
            $archivos = [$archivos];
        }
        $archivos = array_values(array_filter($archivos));

        if (empty($archivos)) {
            return response()->json(
                ApiResponse::error('Debe adjuntar al menos un archivo'),
                422
            );
        }

        return response()->json(
            BlendingCarbonService::subir_evidencias($id_blending_carbon, $archivos)
        );
    }

    /**
     * Elimina una evidencia del blending (archivo fisico + registro).
     */
    public function eliminar_evidencia(Request $request, int $id_blending_carbon): JsonResponse
    {
        $pathRelativo = trim((string) $request->input('path_relativo', ''));

        if ($pathRelativo === '') {
            return response()->json(
                ApiResponse::error('La ruta del archivo es requerida'),
                422
            );
        }

        return response()->json(
            BlendingCarbonService::eliminar_evidencia($id_blending_carbon, $pathRelativo)
        );
    }

    /**
     * Extrae el arreglo de detalles del request (JSON string o array).
     * @return array<int, array<string, mixed>>|null
     */
    private function detalles_from_request(Request $request): ?array
    {
        $raw = $request->input('detalles');

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

        return array_values($raw);
    }

    /**
     * Guarda las evidencias subidas y devuelve su descriptor.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function guardar_evidencias(Request $request): ?array
    {
        $archivos = $request->file('evidencias', []);
        if (!is_array($archivos)) {
            $archivos = [$archivos];
        }
        $archivos = array_values(array_filter($archivos));

        if (empty($archivos)) {
            return null;
        }

        return ArchivoHelper::guardarArchivos(BlendingCarbonService::CARPETA_EVIDENCIAS, $archivos);
    }
}
