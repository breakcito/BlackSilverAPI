<?php

namespace App\Modules\TamizajeCarbon\Service;

use App\Modules\TamizajeCarbon\Data\TamizajeCarbonData;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class TamizajeCarbonService
{
    private const CARPETA_EVIDENCIAS = 'evidencias-tamizaje-carbon';

    /**
     * Lista stock de carbón por almacén.
     * @param array<string, mixed> $opts
     */
    public static function get_stocks(array $opts = []): array
    {
        $rows = TamizajeCarbonData::get_stocks($opts);
        return ApiResponse::success($rows);
    }

    /**
     * Actualiza manualmente el peso del stock con registro de trazabilidad en cambios_log.
     */
    public static function actualizar_stock_manual(
        int $id_stock_carbon,
        float $nuevo_stock,
        int $id_empleado,
        string $nombre_empleado,
        ?string $motivo = null
    ): array {
        $stock = DB::table('stock_carbon')->where('id', $id_stock_carbon)->first();
        if ($stock === null) {
            return ApiResponse::error('Stock no encontrado');
        }

        $stockAnterior = (float) $stock->stock_actual;

        $logEntry = [
            'id_empleado' => $id_empleado,
            'nombre_empleado' => $nombre_empleado,
            'motivo' => $motivo,
            'update_at' => now()->toDateTimeString(),
            'cambios' => [
                [
                    'campo_bd' => 'stock_actual',
                    'campo' => 'Stock Actual (TM)',
                    'valor_anterior' => $stockAnterior,
                    'valor_nuevo' => $nuevo_stock,
                ],
            ],
        ];

        TamizajeCarbonData::actualizar_stock_manual($id_stock_carbon, $nuevo_stock, $logEntry);

        return ApiResponse::success(null, 'Stock de carbón actualizado correctamente');
    }

    /**
     * Lista tamizajes.
     * @param array<string, mixed> $opts
     */
    public static function get_tamizajes(array $opts = []): array
    {
        $rows = TamizajeCarbonData::get_tamizajes($opts);
        return ApiResponse::success($rows);
    }

    /**
     * Lista las cargas de carbón que aún no han sido tamizadas.
     * @param array<string, mixed> $opts
     */
    public static function get_cargas_pendientes(array $opts = []): array
    {
        $rows = TamizajeCarbonData::get_cargas_pendientes($opts);
        return ApiResponse::success($rows);
    }

    /**
     * Registra un nuevo tamizaje de carbón.
     * @param array<string, mixed> $payload
     * @param array<int, UploadedFile> $archivos
     */
    public static function registrar_tamizaje(array $payload, int $id_empleado, array $archivos = []): array
    {
        $idAlmacen = isset($payload['id_almacen']) && (int) $payload['id_almacen'] > 0
            ? (int) $payload['id_almacen']
            : null;

        // Si no viene almacén, auto-elegir el primer almacén de la empresa
        if ($idAlmacen === null) {
            $primerAlmacen = DB::table('almacen')->orderBy('id', 'asc')->first();
            if ($primerAlmacen === null) {
                return ApiResponse::error('No hay almacenes registrados en el sistema');
            }
            $idAlmacen = (int) $primerAlmacen->id;
        }

        $variantes = $payload['variantes'] ?? [];
        if (empty($variantes) || !is_array($variantes)) {
            return ApiResponse::error('Debe ingresar al menos una variante extraída');
        }

        $evidencias = [];
        if (!empty($archivos)) {
            $evidencias = ArchivoHelper::guardarArchivos(self::CARPETA_EVIDENCIAS, $archivos);
        }

        try {
            $idTamizaje = TamizajeCarbonData::registrar_tamizaje([
                'id_almacen' => $idAlmacen,
                'id_empleado_registro' => $id_empleado,
                'id_empleado_supervisor' => isset($payload['id_empleado_supervisor']) && (int) $payload['id_empleado_supervisor'] > 0
                    ? (int) $payload['id_empleado_supervisor']
                    : null,
                'id_tipo_carbon' => (int) $payload['id_tipo_carbon'],
                'id_carga_compra_carbon' => isset($payload['id_carga_compra_carbon']) && (int) $payload['id_carga_compra_carbon'] > 0
                    ? (int) $payload['id_carga_compra_carbon']
                    : null,
                'cantidad_tamizada' => (float) ($payload['cantidad_tamizada'] ?? 0),
                'es_retamizaje' => !empty($payload['es_retamizaje']),
                'fecha_hora_tamizaje' => (string) ($payload['fecha_hora_tamizaje'] ?? now()->toDateTimeString()),
                'evidencias' => $evidencias,
                'variantes' => $variantes,
            ]);

            $tamizajeCreado = TamizajeCarbonData::get_tamizaje_by_id($idTamizaje);

            return ApiResponse::success(
                $tamizajeCreado ?? ['id_tamizaje_carbon' => $idTamizaje],
                'Tamizaje de carbón registrado y stock actualizado satisfactoriamente'
            );
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage());
        }
    }
}
