<?php

namespace App\Controllers;

use App\Shared\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class ArchivoController extends Controller
{
    /**
     * Endpoint para descarga de archivos.
     */
    public function download_archivo(Request $request): Response
    {
        $pathRelativo = $request->input('path_relativo');

        if (!$pathRelativo) {
            return response()->json(ApiResponse::error('Ruta de archivo (path_relativo) requerida'), 400);
        }

        $fullPath = storage_path('app/public/' . ltrim($pathRelativo, '/'));

        if (!file_exists($fullPath)) {
            return response()->json(ApiResponse::error('Archivo no encontrado en el servidor'), 404);
        }

        return response()->download($fullPath);
    }
}
