<?php

namespace App\Modules\TamizajeCarbon\Service;

use App\Modules\TamizajeCarbon\Data\GastoProduccionCarbonData;
use App\Shared\Enums\Carbon\TipoGastoZarandeo;
use App\Shared\Responses\ApiResponse;

class GastoProduccionCarbonService
{
    /**
     * Lista los gastos de producción de un zarandeo.
     */
    public static function get_gastos(int $id_zarandeo_carbon): array
    {
        return ApiResponse::success(GastoProduccionCarbonData::get_gastos($id_zarandeo_carbon));
    }

    /**
     * Registra un gasto de producción asociado a un zarandeo.
     * @param array<string, mixed> $payload
     */
    public static function registrar_gasto(array $payload, int $id_empleado): array
    {
        $idZarandeo = (int) ($payload['id_zarandeo_carbon'] ?? 0);
        if (!GastoProduccionCarbonData::existe_zarandeo($idZarandeo)) {
            return ApiResponse::error('El zarandeo indicado no existe');
        }

        $tipoGasto = TipoGastoZarandeo::tryFrom((string) ($payload['gasto'] ?? ''));
        if ($tipoGasto === null) {
            return ApiResponse::error('El tipo de gasto no es válido');
        }

        $otroGasto = trim((string) ($payload['otro_gasto'] ?? ''));
        if ($tipoGasto === TipoGastoZarandeo::Otro && $otroGasto === '') {
            return ApiResponse::error('Debe describir el concepto del gasto');
        }

        $horaInicio = trim((string) ($payload['hora_inicio'] ?? ''));
        $horaFin = trim((string) ($payload['hora_fin'] ?? ''));

        // Pala y Lanza se tarifan por horas trabajadas.
        if ($tipoGasto->esPorHoras()) {
            if ($horaInicio === '' || $horaFin === '') {
                return ApiResponse::error('Para gastos por hora debe indicar la hora de inicio y la hora de fin');
            }
            $cantidad = self::calcular_horas($horaInicio, $horaFin);
            if ($cantidad === null) {
                return ApiResponse::error('Las horas indicadas no son válidas');
            }
        } else {
            $cantidad = (float) ($payload['cantidad'] ?? 0);
        }

        $precioUnitario = (float) ($payload['precio_unitario'] ?? 0);
        $total = round($cantidad * $precioUnitario, 2);

        $idGasto = GastoProduccionCarbonData::insert_gasto([
            'id_empleado_registro' => $id_empleado,
            'id_zarandeo_carbon' => $idZarandeo,
            'id_proveedor' => $payload['id_proveedor'] ?? null,
            'gasto' => $tipoGasto->value,
            'otro_gasto' => $otroGasto !== '' ? $otroGasto : null,
            'descripcion' => $payload['descripcion'] ?? null,
            'fecha' => (string) $payload['fecha'],
            'numero_reporte' => $payload['numero_reporte'] ?? null,
            'hora_inicio' => $horaInicio !== '' ? $horaInicio : null,
            'hora_fin' => $horaFin !== '' ? $horaFin : null,
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'total' => $total,
        ]);

        // Se devuelve el gasto ya persistido con la misma forma que el listado,
        // para que el front lo inserte en su lista sin volver a consultar.
        $nuevoGasto = GastoProduccionCarbonData::get_gasto_by_id($idZarandeo, $idGasto);

        return ApiResponse::success(
            $nuevoGasto,
            'Gasto de producción registrado'
        );
    }

    /**
     * Diferencia absoluta en horas entre dos marcas de tiempo (HH:MM).
     */
    private static function calcular_horas(string $horaInicio, string $horaFin): ?float
    {
        $inicio = strtotime($horaInicio);
        $fin = strtotime($horaFin);

        if ($inicio === false || $fin === false) {
            return null;
        }

        return round(abs($fin - $inicio) / 3600, 4);
    }
}
