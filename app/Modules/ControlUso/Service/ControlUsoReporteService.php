<?php

namespace App\Modules\ControlUso\Service;

use App\Models\Empresa;
use App\Modules\ControlUso\Data\ControlUsoReporteData;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\Log;

class ControlUsoReporteService
{
    /**
     * Obtener el reporte mensual de uso y mantenimientos.
     */
    public static function get_reporte_mensual(int $mes, int $anio)
    {
        try {
            $logs = ControlUsoReporteData::get_logs_por_mes($mes, $anio);
            $mantenimientos = ControlUsoReporteData::get_mantenimientos_por_mes($mes, $anio);

            $empresa = Empresa::first();
            $empresa_logo = null;
            if ($empresa && $empresa->url_logo) {
                $empresa_logo = self::logo_a_base64($empresa->url_logo);
            }

            return ApiResponse::success([
                'logs' => $logs,
                'mantenimientos' => $mantenimientos,
                'empresa_logo' => $empresa_logo
            ], 'Reporte generado');
        } catch (\Exception $e) {
            Log::error('Error al generar reporte mensual de control de uso: ' . $e->getMessage());
            return ApiResponse::error('Ocurrió un error al generar el reporte.');
        }
    }

    /**
     * Convierte un url_logo a data URL base64.
     */
    private static function logo_a_base64(string $logo): ?string
    {
        if (str_starts_with($logo, 'http')) {
            $parsed = parse_url($logo, PHP_URL_PATH);
            $relativePath = ltrim(str_replace('/storage/', '', $parsed ?? ''), '/');
        } else {
            $relativePath = ltrim($logo, '/');
        }

        $fullPath = storage_path('app/public/' . $relativePath);
        if (!file_exists($fullPath))
            return null;

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg',
        };

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($fullPath));
    }
}
