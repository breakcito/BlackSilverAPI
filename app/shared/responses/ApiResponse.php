<?php

namespace App\Shared\Responses;

use Illuminate\Http\JsonResponse;

/**
 * Clase para respuestas API estandarizadas.
 */
class ApiResponse
{
    /**
     * Respuesta exitosa.
     */
    public static function success($data = null, ?string $message = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
        ], 200);
    }

    /**
     * Respuesta de error.
     */
    public static function error(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => null,
            'message' => $message,
        ], 400);
    }

    /**
     * Respuesta de no autorizado.
     */
    public static function unauthorized(string $message = 'No autorizado'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => null,
            'message' => $message,
        ], 401);
    }

    /**
     * Respuesta de error del servidor.
     */
    public static function serverError(string $message = 'Ups! Algo salio mal...'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => null,
            'message' => $message,
        ], 500);
    }

    /**
     * Genera respuesta estándar para uso en servicios.
     */
    public static function array(bool $success, $data = null, ?string $message = null): array
    {
        return [
            'success' => $success,
            'data' => $data,
            'message' => $message,
        ];
    }
}
