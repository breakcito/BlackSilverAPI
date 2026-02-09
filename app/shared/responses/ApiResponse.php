<?php

namespace App\Shared\Responses;


/**
 * Clase para respuestas API estandarizadas.
 */
class ApiResponse
{
    public static function success($data = null, ?string $error = null)
    {
        return [
            'success' => true,
            'data' => $data,
            'error' => $error,
        ];
    }

    public static function error($error = null)
    {
        return [
            'success' => false,
            'data' => null,
            'error' => $error,
        ];
    }
}
