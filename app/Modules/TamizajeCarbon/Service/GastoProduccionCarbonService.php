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
    public static function get_gastos_zarandeo(int $id_zarandeo_carbon): array
    {
        return ApiResponse::success(
            GastoProduccionCarbonData::get_gastos(id_zarandeo_carbon: $id_zarandeo_carbon)
        );
    }

    /**
     * Lista los gastos de producción de un blending.
     */
    public static function get_gastos_blending(int $id_blending_carbon): array
    {
        return ApiResponse::success(
            GastoProduccionCarbonData::get_gastos(id_blending_carbon: $id_blending_carbon)
        );
    }

    /**
     * Gastos de producción listos para exportar.
     *
     * Sin destino: los gastos del tipo pedido (blending o zarandeo) cuyo
     * destino cae en el rango de fechas. Con destino: solo los de ese
     * registro.
     *
     * @param ?string $fecha_inicio Rango por la fecha inicio del destino.
     * @param ?string $fecha_fin Rango por la fecha inicio del destino.
     * @param ?string $tipo "blending" o "zarandeo". Null = de los dos.
     */
    public static function get_gastos_exportar(
        ?int $id_zarandeo_carbon = null,
        ?int $id_blending_carbon = null,
        ?string $fecha_inicio = null,
        ?string $fecha_fin = null,
        ?string $tipo = null
    ): array
    {
        $gastos = GastoProduccionCarbonData::get_gastos_exportar(
            id_zarandeo_carbon: $id_zarandeo_carbon,
            id_blending_carbon: $id_blending_carbon,
            fecha_inicio: $fecha_inicio,
            fecha_fin: $fecha_fin,
            tipo: $tipo
        );

        return ApiResponse::success($gastos);
    }

    /**
     * Confirma el pago de un gasto de producción.
     *
     * La fecha de pago puede ser retroactiva.
     */
    public static function confirmar_pago_gasto(int $id_gasto, string $fecha_pago): array
    {
        if (!GastoProduccionCarbonData::existe_gasto($id_gasto)) {
            return ApiResponse::error('El gasto indicado no existe');
        }

        $actualizado = GastoProduccionCarbonData::actualizar_pago_gasto($id_gasto, $fecha_pago);

        if (!$actualizado) {
            return ApiResponse::error('No se pudo registrar el pago del gasto');
        }

        $gasto = GastoProduccionCarbonData::get_gastos(id_gasto: $id_gasto);

        return ApiResponse::success($gasto, 'Pago del gasto registrado');
    }

    /**
     * Registra un gasto de producción asociado a un zarandeo o a un blending.
     *
     * @param array<string, mixed> $payload
     */
    public static function registrar_gasto(array $payload, int $id_empleado): array
    {
        $idZarandeo = (int) ($payload['id_zarandeo_carbon'] ?? 0);
        $idBlending = (int) ($payload['id_blending_carbon'] ?? 0);

        // Un gasto tiene UN destino: el zarandeo o el blending.
        if ($idZarandeo <= 0 && $idBlending <= 0) {
            return ApiResponse::error('Debe indicar el zarandeo o el blending del gasto');
        }

        if ($idZarandeo > 0 && $idBlending > 0) {
            return ApiResponse::error('El gasto no puede apuntar a un zarandeo y a un blending a la vez');
        }

        if ($idZarandeo > 0 && !GastoProduccionCarbonData::existe_zarandeo($idZarandeo)) {
            return ApiResponse::error('El zarandeo indicado no existe');
        }

        if ($idBlending > 0 && !GastoProduccionCarbonData::existe_blending($idBlending)) {
            return ApiResponse::error('El blending indicado no existe');
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
            'id_zarandeo_carbon' => $idZarandeo > 0 ? $idZarandeo : null,
            'id_blending_carbon' => $idBlending > 0 ? $idBlending : null,
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
        $nuevoGasto = GastoProduccionCarbonData::get_gasto_by_id(
            $idZarandeo > 0 ? $idZarandeo : null,
            $idBlending > 0 ? $idBlending : null,
            $idGasto
        );

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
