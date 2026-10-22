<?php

namespace App\Modules\TamizajeCarbon\Service;

use App\Modules\TamizajeCarbon\Data\TamizajeCarbonData;
use App\Shared\Enums\Carbon\TipoCalidadCarbon;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class TamizajeCarbonService
{
    private const CARPETA_EVIDENCIAS = 'evidencias-tamizaje-carbon';

    /**
     * Lista stock de carbón por almacén, tipo y calidad.
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
     * Lista los zarandeos realizados.
     * @param array<string, mixed> $opts
     */
    public static function get_tamizajes(array $opts = []): array
    {
        $rows = TamizajeCarbonData::get_tamizajes($opts);
        return ApiResponse::success($rows);
    }

    /**
     * Lista los grupos de cargas pendientes de zarandeo (por tipo de carbón y calidad).
     * @param array<string, mixed> $opts
     */
    public static function get_grupos_cargas(array $opts = []): array
    {
        $rows = TamizajeCarbonData::get_grupos_cargas($opts);
        return ApiResponse::success($rows);
    }

    /**
     * Registra un nuevo zarandeo de carbón.
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

        $calidad = isset($payload['calidad']) ? trim((string) $payload['calidad']) : '';
        if ($calidad === '') {
            return ApiResponse::error('Debe indicar la calidad del carbón a zarandear');
        }
        if (TipoCalidadCarbon::tryFrom($calidad) === null) {
            return ApiResponse::error('La calidad indicada no es válida');
        }

        $idCargas = array_values(array_unique(array_map('intval', (array) ($payload['id_cargas'] ?? []))));
        $idCargas = array_values(array_filter($idCargas, fn ($id) => $id > 0));

        // --- Validación del grupo de cargas ---
        // La asignacion definitiva se hace bloqueada dentro de la transaccion
        // (Data); aqui solo se valida que el grupo sea coherente.
        $cantidadGrupo = 0.0;
        if (!empty($idCargas)) {
            $cargas = DB::table('carga_compra_carbon')
                ->whereIn('id', $idCargas)
                ->get();

            if ($cargas->count() !== count($idCargas)) {
                return ApiResponse::error('Alguna de las cargas seleccionadas ya no existe');
            }

            foreach ($cargas as $carga) {
                if ($carga->id_zarandeo_carbon !== null) {
                    return ApiResponse::error('Alguna de las cargas seleccionadas ya fue zarandeada');
                }
                if ((int) $carga->id_almacen_empresa_llegada !== $idAlmacen) {
                    return ApiResponse::error('Las cargas seleccionadas pertenecen a distintos almacenes');
                }
                if ((int) $carga->id_tipo_carbon !== (int) $payload['id_tipo_carbon']) {
                    return ApiResponse::error('Las cargas seleccionadas son de distintos tipos de carbón');
                }
                if ((string) $carga->calidad !== $calidad) {
                    return ApiResponse::error('Las cargas seleccionadas son de distintas calidades');
                }
                $cantidadGrupo += (float) $carga->cantidad;
            }
        }

        // --- Cantidad a zarandear ---
        $cantidadZarandeada = isset($payload['cantidad_zarandeada']) && (float) $payload['cantidad_zarandeada'] > 0
            ? (float) $payload['cantidad_zarandeada']
            : $cantidadGrupo;

        if ($cantidadZarandeada <= 0) {
            return ApiResponse::error('La cantidad a zarandear debe ser mayor a cero');
        }

        if (!empty($idCargas) && $cantidadZarandeada > round($cantidadGrupo, 4)) {
            return ApiResponse::error(
                'La cantidad a zarandear no puede superar el total del grupo de cargas ('
                . rtrim(rtrim(number_format($cantidadGrupo, 2), '0'), '.') . ' TN)'
            );
        }

        // --- Cálculo de extracciones por variante ---
        $variantesCalculo = [];
        $cantidadExtraidaTotal = 0.0;
        $sumaPorcentajes = 0.0;
        $idsVariantes = [];

        foreach ($variantes as $v) {
            $idTipoVariante = (int) ($v['id_tipo_variante'] ?? 0);
            if ($idTipoVariante <= 0) {
                continue;
            }
            if (isset($idsVariantes[$idTipoVariante])) {
                return ApiResponse::error('No se puede registrar dos veces la misma variante');
            }
            $idsVariantes[$idTipoVariante] = true;

            $porcentaje = (float) ($v['porcentaje_extraccion'] ?? 0);
            $sumaPorcentajes += $porcentaje;
            $cantidadExtraida = round($cantidadZarandeada * $porcentaje / 100, 4);
            $cantidadExtraidaTotal += $cantidadExtraida;

            $variantesCalculo[] = [
                'id_tipo_variante' => $idTipoVariante,
                'porcentaje_extraccion' => $porcentaje,
                'cantidad_extraida' => $cantidadExtraida,
            ];
        }

        if (empty($variantesCalculo)) {
            return ApiResponse::error('Debe ingresar al menos una variante extraída');
        }

        if (round($sumaPorcentajes, 4) > 100.0001) {
            return ApiResponse::error(
                'La suma de los porcentajes de extracción no puede superar el 100% (actual: '
                . rtrim(rtrim(number_format($sumaPorcentajes, 2), '0'), '.') . '%)'
            );
        }

        if ($cantidadExtraidaTotal <= 0) {
            return ApiResponse::error('El total extraído debe ser mayor a cero');
        }

        // --- Stock disponible del tipo de carbón zarandeado ---
        $stockDisponible = TamizajeCarbonData::get_stock_disponible(
            idAlmacen: $idAlmacen,
            idTipoCarbon: (int) $payload['id_tipo_carbon'],
            calidad: $calidad
        );

        if ($stockDisponible + 0.0001 < $cantidadZarandeada) {
            return ApiResponse::error(
                'No hay stock suficiente de la calidad seleccionada para zarandear '
                . rtrim(rtrim(number_format($cantidadZarandeada, 2), '0'), '.') . ' TN (disponible: '
                . rtrim(rtrim(number_format($stockDisponible, 2), '0'), '.') . ' TN)'
            );
        }

        if ($stockDisponible + 0.0001 < $cantidadExtraidaTotal) {
            return ApiResponse::error(
                'El total extraído supera el stock disponible (disponible: '
                . rtrim(rtrim(number_format($stockDisponible, 2), '0'), '.') . ' TN)'
            );
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
                'calidad' => $calidad,
                'cantidad_zarandeada' => $cantidadZarandeada,
                'es_rezarandeo' => empty($idCargas),
                'id_cargas' => $idCargas,
                'fecha_hora_zarandeo' => (string) ($payload['fecha_hora_zarandeo'] ?? now()->toDateTimeString()),
                'evidencias' => $evidencias,
                'variantes' => $variantesCalculo,
            ]);

            $tamizajeCreado = TamizajeCarbonData::get_tamizaje_by_id($idTamizaje);

            return ApiResponse::success(
                $tamizajeCreado ?? ['id_zarandeo_carbon' => $idTamizaje],
                'Zarandeo de carbón registrado y stock actualizado satisfactoriamente'
            );
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage());
        }
    }
}
