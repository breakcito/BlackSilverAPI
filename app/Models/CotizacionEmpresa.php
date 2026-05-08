<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Tabla que permite asociar varias empresas a una cotizacion
 */
class CotizacionEmpresa extends Model
{
    protected $table = 'cotizacion_empresa';

    public $timestamps = false;

    protected $fillable = [
        'id_cotizacion',
        'id_empresa'
    ];

    public static function asignar_empresa(
        int $id_cotizacion,
        array $ids_empresas
    ): bool {
        $insertData = [];

        foreach ($ids_empresas as $id_emp) {
            $insertData[] = [
                'id_cotizacion' => $id_cotizacion,
                'id_empresa' => (int) $id_emp,
            ];
        }

        if (empty($insertData)) {
            return true;
        }

        return self::insert($insertData);
    }

    /**
     * Obtener las empresas asociadas a un grupo de cotizaciones o todas
     */
    public static function get_empresas(null|int|array $ids_cotizaciones = null): array
    {
        $sql = "
            SELECT
                ce.id_cotizacion,
                ce.id_empresa,
                emp.razon_social,
                emp.path_logo
            FROM cotizacion_empresa ce
            INNER JOIN empresa emp ON emp.id = ce.id_empresa
            WHERE 1 = 1
        ";

        $params = [];

        if ($ids_cotizaciones !== null) {
            if (is_array($ids_cotizaciones)) {
                // Si es array, generamos placeholders para el IN
                $placeholders = implode(',', array_fill(0, count($ids_cotizaciones), '?'));
                $sql .= " AND ce.id_cotizacion IN ({$placeholders})";
                $params = array_merge($params, array_values($ids_cotizaciones));
            } else {
                // Si es un solo ID
                $sql .= " AND ce.id_cotizacion = ?";
                $params[] = $ids_cotizaciones;
            }
        }

        $sql .= " ORDER BY emp.razon_social ASC";

        $results = DB::select($sql, $params);

        // Embeber logo como base64 en la respuesta JSON
        // Evita requests adicionales desde el frontend (php artisan serve es single-threaded)
        foreach ($results as $row) {
            if ($row->path_logo) {
                // Normalizar a ruta relativa
                if (str_starts_with($row->path_logo, 'http')) {
                    $parsed = parse_url($row->path_logo, PHP_URL_PATH);
                    $relativePath = ltrim(str_replace('/storage/', '', $parsed ?? ''), '/');
                } else {
                    $relativePath = ltrim($row->path_logo, '/');
                }

                $fullPath = storage_path('app/public/' . $relativePath);
                if (file_exists($fullPath)) {
                    $ext  = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
                    $mime = match ($ext) {
                        'png'  => 'image/png',
                        'gif'  => 'image/gif',
                        'webp' => 'image/webp',
                        'svg'  => 'image/svg+xml',
                        default => 'image/jpeg',
                    };
                    $row->path_logo = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($fullPath));
                } else {
                    $row->path_logo = null;
                }
            }
        }

        return $results;
    }
}
