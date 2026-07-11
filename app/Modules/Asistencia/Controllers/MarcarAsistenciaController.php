<?php

namespace App\Modules\Asistencia\Controllers;

use App\Modules\Asistencia\Services\AsistenciaService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

/**
 * Endpoints públicos del flujo de marcaje por QR (/marcar-asistencia).
 *
 * Estos endpoints NO usan `auth.jwt.custom` (la ruta es plana y el empleado
 * no necesita sesión iniciada). La seguridad se delega a la validación
 * del `qr_token`.
 *
 * Flujo:
 *  - `resolver_qr` (paso 1): valida el QR y devuelve un `id_sesion` (UUID).
 *  - `confirmar_asistencia` (paso final): crea el marcaje exitoso + asistencia.
 *  - `cancelar_proceso`: crea un marcaje incompleto (proceso_confirmado=false).
 *
 * El marcaje SOLO se crea cuando el proceso termina (éxito o cancelación).
 * NUNCA se crea durante el flujo.
 */
class MarcarAsistenciaController extends Controller
{
    /**
     * Paso 1: Resuelve el QR token. NO crea el marcaje; devuelve un
     * `id_sesion` (UUID) que el frontend conservará hasta confirmar/cancelar.
     */
    public function resolver_qr(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'qr_token' => 'required|string|min:8|max:255',
            'evidencia_inicial' => 'nullable|array',
            'evidencia_inicial.url' => 'required_with:evidencia_inicial|string',
            'evidencia_inicial.path_relativo' => 'required_with:evidencia_inicial|string',
            'evidencia_inicial.nombre_original' => 'nullable|string',
            'evidencia_inicial.extension' => 'nullable|string',
        ], [
            'qr_token.required' => 'El código QR es obligatorio.',
            'qr_token.min' => 'El código QR es demasiado corto.',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $evidencia = $request->input('evidencia_inicial');
        $result = AsistenciaService::resolver_qr((string) $request->input('qr_token'), $evidencia);

        return response()->json($result);
    }

    /**
     * Paso final: confirma el proceso. Crea el marcaje exitoso + la
     * asistencia (si todo está OK). El frontend envía `id_sesion` + `id_empleado`
     * (no más `id_marcaje` porque el marcaje aún no existe).
     */
    public function confirmar_asistencia(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_sesion' => 'required|string|max:64',
            'id_empleado' => 'required|integer|min:1',
            'evidencia_rostro' => 'nullable|array',
            'evidencia_rostro.url' => 'required_with:evidencia_rostro|string',
            'evidencia_rostro.path_relativo' => 'required_with:evidencia_rostro|string',
            'evidencia_rostro.nombre_original' => 'nullable|string',
            'evidencia_rostro.extension' => 'nullable|string',
            'evidencia_qr' => 'nullable|array',
            'evidencia_qr.url' => 'required_with:evidencia_qr|string',
            'evidencia_qr.path_relativo' => 'required_with:evidencia_qr|string',
            'evidencia_qr.nombre_original' => 'nullable|string',
            'evidencia_qr.extension' => 'nullable|string',
        ], [
            'id_sesion.required' => 'Falta el identificador de sesión del proceso.',
            'id_empleado.required' => 'Falta el identificador del empleado.',
            'id_empleado.integer' => 'El identificador del empleado no es válido.',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = AsistenciaService::confirmar_asistencia(
            id_marcaje: 0,
            evidencia_rostro: $request->input('evidencia_rostro'),
            id_empleado_registro: null,
            id_sesion: (string) $request->input('id_sesion'),
            id_empleado_param: (int) $request->input('id_empleado'),
            evidencia_qr: $request->input('evidencia_qr'),
        );

        return response()->json($result);
    }

    /**
     * Cancelación: crea un marcaje incompleto (proceso_confirmado=false).
     * Llamado desde el frontend cuando el usuario:
     *  - Cancelan manualmente desde el paso 2/3/4.
     *  - Cierra la pestaña mientras hay un proceso en curso.
     *  - El session timeout expira.
     */
    public function cancelar_proceso(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_empleado' => 'required|integer|min:1',
            'llego_al_qr' => 'nullable|boolean',
            'id_sesion' => 'nullable|string|max:64',
            'motivo' => 'nullable|string|max:255',
            'evidencia_qr' => 'nullable|array',
            'evidencia_qr.url' => 'required_with:evidencia_qr|string',
            'evidencia_qr.path_relativo' => 'required_with:evidencia_qr|string',
            'evidencia_qr.nombre_original' => 'nullable|string',
            'evidencia_qr.extension' => 'nullable|string',
        ], [
            'id_empleado.required' => 'Falta el identificador del empleado.',
            'id_empleado.integer' => 'El identificador del empleado no es válido.',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = AsistenciaService::cancelar_proceso(
            id_empleado: (int) $request->input('id_empleado'),
            llego_al_qr: (bool) $request->boolean('llego_al_qr'),
            id_sesion: $request->input('id_sesion'),
            motivo: $request->input('motivo'),
            evidencia_qr: $request->input('evidencia_qr'),
        );

        return response()->json($result);
    }
}
