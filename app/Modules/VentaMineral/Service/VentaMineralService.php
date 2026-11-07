<?php

namespace App\Modules\VentaMineral\Service;

use App\Modules\VentaMineral\Data\VentaMineralAnalisisLiquidacionData;
use App\Modules\VentaMineral\Data\VentaMineralComprobanteData;
use App\Modules\VentaMineral\Data\VentaMineralData;
use App\Modules\VentaMineral\Data\VentaMineralGastoFleteData;
use App\Shared\Enums\LoteMineral\EstadoLoteMineral;
use App\Shared\Enums\VentaMineral\TipoGastoDespacho;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

/**
 * Orquestacion de los despachos de mineral a molienda.
 */
class VentaMineralService
{
    /**
     * Lista los despachos.
     */
    public static function get_despachos(
        ?int $mes = null,
        ?int $anio = null,
        ?string $estado = null,
        ?int $id_lote_mineral = null
    ): array {
        return ApiResponse::success(
            VentaMineralData::get_despachos($mes, $anio, $estado, $id_lote_mineral)
        );
    }

    /**
     * Lotes finalizados todavia sin despacho.
     */
    public static function get_lotes_despachables(): array
    {
        return ApiResponse::success(VentaMineralData::get_lotes_despachables());
    }

    /**
     * Detalle de un despacho con todo su arbol: gastos, fletes, comprobantes
     * y pagos, analisis y liquidaciones.
     */
    public static function get_despacho(int $id_despacho_mineral): array
    {
        $despacho = VentaMineralData::get_despacho($id_despacho_mineral);

        if ($despacho === null) {
            return ApiResponse::error('Despacho no encontrado', 404);
        }

        $despacho->gastos = VentaMineralGastoFleteData::get_gastos($id_despacho_mineral);
        $despacho->fletes = VentaMineralGastoFleteData::get_fletes($id_despacho_mineral);

        $despacho->comprobantes_flete = self::adjuntar_pagos(
            'flete',
            VentaMineralComprobanteData::get_comprobantes('flete', $id_despacho_mineral)
        );

        $despacho->comprobantes_molienda = self::adjuntar_pagos(
            'molienda',
            VentaMineralComprobanteData::get_comprobantes('molienda', $id_despacho_mineral)
        );

        $despacho->comprobantes_analisis = self::adjuntar_pagos(
            'analisis',
            VentaMineralComprobanteData::get_comprobantes('analisis', $id_despacho_mineral)
        );

        $despacho->comprobantes_liquidacion = self::adjuntar_pagos(
            'liquidacion',
            VentaMineralComprobanteData::get_comprobantes('liquidacion', $id_despacho_mineral)
        );

        // Cada comprobante de flete lleva los fletes que factura.
        $idsComprobantes = array_map(
            fn ($c) => (int) $c->id_comprobante,
            $despacho->comprobantes_flete
        );
        $fletesPorComprobante = VentaMineralGastoFleteData::get_fletes_por_comprobante($idsComprobantes);
        foreach ($despacho->comprobantes_flete as $comprobante) {
            $comprobante->fletes = $fletesPorComprobante[(int) $comprobante->id_comprobante] ?? [];
        }

        // Cada comprobante de analisis lleva los analisis que factura.
        $despacho->analisis = VentaMineralAnalisisLiquidacionData::get_analisis($id_despacho_mineral);
        $idsComprobantesAnalisis = array_map(
            fn ($c) => (int) $c->id_comprobante,
            $despacho->comprobantes_analisis
        );
        $analisisPorComprobante = VentaMineralAnalisisLiquidacionData::get_analisis_por_comprobante($idsComprobantesAnalisis);
        foreach ($despacho->comprobantes_analisis as $comprobante) {
            $comprobante->analisis = $analisisPorComprobante[(int) $comprobante->id_comprobante] ?? [];
        }

        $despacho->liquidaciones = VentaMineralAnalisisLiquidacionData::get_liquidaciones($id_despacho_mineral);

        return ApiResponse::success($despacho);
    }

    /**
     * Registra un despacho de mineral a molienda CON su flete y los gastos
     * iniciales.
     *
     * El flete va en el mismo alta: el mineral del despacho se lleva en una
     * sola unidad, asi que la carga no existe sin ella. Todo se graba en una
     * sola transaccion: si el flete o un gasto fallan, no queda un despacho
     * a medias.
     *
     * @param array<string, mixed> $payload
     */
    public static function registrar_despacho(array $payload, int $id_empleado): array
    {
        $error = self::validar_despacho($payload);
        if ($error !== null) {
            return ApiResponse::error($error);
        }

        $flete = $payload['flete'] ?? null;
        $errorFlete = VentaMineralGastoFleteService::validar_flete((array) $flete);
        if ($errorFlete !== null) {
            return ApiResponse::error('Flete: ' . $errorFlete);
        }

        $gastos = $payload['gastos'] ?? [];
        if (!is_array($gastos)) {
            $gastos = [];
        }

        foreach ($gastos as $indice => $gasto) {
            $errorGasto = self::validar_gasto((array) $gasto);
            if ($errorGasto !== null) {
                return ApiResponse::error('Gasto #' . ($indice + 1) . ': ' . $errorGasto);
            }
        }

        try {
            $idDespacho = DB::transaction(function () use ($payload, $id_empleado, $flete, $gastos) {
                $id = VentaMineralData::registrar_despacho([
                    'id_lote_mineral' => (int) $payload['id_lote_mineral'],
                    'id_molino' => (int) $payload['id_molino'],
                    'id_empleado_registro' => $id_empleado,
                    'fecha_despacho' => (string) $payload['fecha_despacho'],
                    'cantidad_sacos' => (float) $payload['cantidad_sacos'],
                    'observaciones' => $payload['observaciones'] ?? null,
                ]);

                // El flete es la carga: se registra con el despacho.
                VentaMineralGastoFleteData::registrar_flete([
                    'id_despacho_mineral' => $id,
                    'id_transportista_mineral' => (int) $flete['id_transportista_mineral'],
                    'id_empleado_registro' => $id_empleado,
                    'placa' => $flete['placa'] ?? null,
                    'licencia_conductor' => $flete['licencia_conductor'] ?? null,
                    'guia_remitente' => $flete['guia_remitente'] ?? null,
                    'peso_guia' => (float) ($flete['peso_guia'] ?? 0),
                    // La balanza se registra despues, en su propia operacion.
                    'largo' => (float) ($flete['largo'] ?? 0),
                    'ancho' => (float) ($flete['ancho'] ?? 0),
                    'alto' => (float) ($flete['alto'] ?? 0),
                    'evidencias' => $flete['evidencias'] ?? null,
                ]);

                // Los gastos del despacho (winche, winchadores).
                foreach ($gastos as $gasto) {
                    VentaMineralGastoFleteData::registrar_gasto([
                        'id_despacho_mineral' => $id,
                        'id_empleado_registro' => $id_empleado,
                        'tipo_gasto' => (string) $gasto['tipo_gasto'],
                        'descripcion' => $gasto['descripcion'] ?? null,
                        'monto' => (float) $gasto['monto'],
                    ]);
                }

                VentaMineralData::recalcular_estado_despacho($id);

                return $id;
            });
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage());
        }

        $despacho = VentaMineralData::get_despacho($idDespacho);

        return ApiResponse::success($despacho, 'Despacho registrado correctamente');
    }

    /**
     * Edita un despacho de mineral.
     *
     * @param array<string, mixed> $payload
     */
    public static function actualizar_despacho(int $id_despacho_mineral, array $payload, int $id_empleado): array
    {
        $despacho = VentaMineralData::get_despacho($id_despacho_mineral);

        if ($despacho === null) {
            return ApiResponse::error('Despacho no encontrado', 404);
        }

        $error = self::validar_despacho($payload);
        if ($error !== null) {
            return ApiResponse::error($error);
        }

        VentaMineralData::actualizar_despacho([
            'id_despacho_mineral' => $id_despacho_mineral,
            'id_molino' => (int) $payload['id_molino'],
            'fecha_despacho' => (string) $payload['fecha_despacho'],
            'cantidad_sacos' => (float) $payload['cantidad_sacos'],
            'observaciones' => $payload['observaciones'] ?? null,
        ]);

        $actualizado = VentaMineralData::get_despacho($id_despacho_mineral);

        return ApiResponse::success($actualizado, 'Despacho actualizado correctamente');
    }

    /**
     * Elimina un despacho con todo su arbol.
     *
     * Si ya hay pagos registrados, el despacho no se puede eliminar: el
     * registro financiero debe quedarse.
     */
    public static function eliminar_despacho(int $id_despacho_mineral): array
    {
        $despacho = VentaMineralData::get_despacho($id_despacho_mineral);

        if ($despacho === null) {
            return ApiResponse::error('Despacho no encontrado', 404);
        }

        foreach (['flete', 'molienda', 'analisis', 'liquidacion'] as $tipo) {
            $comprobantes = VentaMineralComprobanteData::get_comprobantes($tipo, $id_despacho_mineral);
            foreach ($comprobantes as $comprobante) {
                if (VentaMineralComprobanteData::contar_pagos($tipo, (int) $comprobante->id_comprobante) > 0) {
                    return ApiResponse::error(
                        'No se puede eliminar el despacho: tiene pagos registrados. '
                        . 'Elimina o anula los pagos primero.',
                        409
                    );
                }
            }
        }

        VentaMineralData::eliminar_despacho($id_despacho_mineral);

        return ApiResponse::success(null, 'Despacho eliminado correctamente');
    }

    /**
     * Valida los datos de un gasto del despacho.
     *
     * @param array<string, mixed> $payload
     */
    private static function validar_gasto(array $payload): ?string
    {
        $tipo = (string) ($payload['tipo_gasto'] ?? '');
        if (!in_array($tipo, TipoGastoDespacho::valores(), true)) {
            return 'El tipo de gasto no es valido';
        }

        if ((float) ($payload['monto'] ?? 0) < 0) {
            return 'El monto del gasto no puede ser negativo';
        }

        return null;
    }

    /**
     * Valida los datos de un despacho.
     *
     * @param array<string, mixed> $payload
     */
    private static function validar_despacho(array $payload): ?string
    {
        $idLote = (int) ($payload['id_lote_mineral'] ?? 0);
        if ($idLote <= 0) {
            return 'Debe seleccionar el lote de mineral a despachar';
        }

        $idMolino = (int) ($payload['id_molino'] ?? 0);
        if ($idMolino <= 0) {
            return 'Debe seleccionar el molino de destino';
        }

        if (VentaMineralCatalogoService::get_molino($idMolino) === null) {
            return 'El molino de destino no existe';
        }

        $disponibilidad = VentaMineralData::sacos_disponibles_lote($idLote);
        $lote = $disponibilidad['lote'];

        if ($lote === null) {
            return 'El lote de mineral no existe';
        }

        if ($lote->estado !== EstadoLoteMineral::Finalizado->value) {
            return 'Solo se pueden despachar lotes de mineral finalizados';
        }

        // Opcion A: un lote se despacha una sola vez. Para permitir despachos
        // parciales hay que revisar `sacos_disponibles_lote` (unico punto de
        // extension del modulo).
        if ($disponibilidad['despacho_existente'] !== null) {
            return 'El lote ' . ($lote->codigo ?? $idLote)
                . ' ya fue despachado. No se puede despachar dos veces.';
        }

        $cantidadSacos = (float) ($payload['cantidad_sacos'] ?? 0);
        if ($cantidadSacos <= 0) {
            return 'La cantidad de sacos debe ser mayor a 0';
        }

        if ($cantidadSacos > (float) $lote->cantidad_sacos + 0.0001) {
            return 'La cantidad de sacos no puede ser mayor que los sacos del lote ('
                . rtrim(rtrim(number_format((float) $lote->cantidad_sacos, 2), '0'), '.') . ')';
        }

        return null;
    }

    /**
     * Adjunta los pagos a un conjunto de comprobantes.
     *
     * @param array<int, object> $comprobantes
     * @return array<int, object>
     */
    private static function adjuntar_pagos(string $tipo, array $comprobantes): array
    {
        if (empty($comprobantes)) {
            return [];
        }

        $ids = array_map(fn ($c) => (int) $c->id_comprobante, $comprobantes);
        $pagosPorComprobante = VentaMineralComprobanteData::get_pagos($tipo, $ids);

        foreach ($comprobantes as $comprobante) {
            $comprobante->pagos = $pagosPorComprobante[(int) $comprobante->id_comprobante] ?? [];
        }

        return $comprobantes;
    }
}
