<?php

namespace App\Shared\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArchivoHelper
{
    /**
     * Guarda un array de archivos de forma segura en el disco local.
     *
     * @param  string  $carpetaDestino  Carpeta dentro de storage/app (ej. "pagos").
     * @param  UploadedFile[]  $archivos  Archivos recibidos en el request.
     * @return array Retorna: { path (ruta absoluta), filename (nombre original limpio), extension }
     */
    public static function guardarArchivos(string $carpetaDestino, array $archivos): array
    {
        $resultados = [];

        // Agrupamos los archivos en subcarpetas por fecha (ej. "pagos/28-02-26")
        // para evitar tener miles de archivos en una sola carpeta
        $rutaDestino = trim($carpetaDestino, '/').'/'.date('d-m-y');

        foreach ($archivos as $archivo) {
            // Ignoramos lo que no sea un archivo o haya llegado corrupto al servidor
            if (! $archivo instanceof UploadedFile || ! $archivo->isValid()) {
                continue;
            }

            // Extraemos el nombre original y lo limpiamos
            $nombreOriginal = pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME);
            $nombreLimpio = Str::slug($nombreOriginal);
            $extension = $archivo->getClientOriginalExtension() ?: ($archivo->guessExtension() ?? 'bin');

            // Usamos store() para guardar y laravel automáticamente generará
            // un hash único. Ademas creará la carpeta si no existe y guardará el archivo.
            $pathRelativo = $archivo->store($rutaDestino, ['disk' => 'local']);

            // Si el guardado fue exitoso, construimos el array con la información requerida.
            if ($pathRelativo) {
                $resultados[] = [
                    'path' => Storage::disk('local')->path($pathRelativo),
                    'filename' => $nombreLimpio,
                    'extension' => $extension,
                ];
            }
        }

        return $resultados;
    }
}
