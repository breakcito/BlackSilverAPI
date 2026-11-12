<?php

namespace App\Modules\VentaMineral\Service;

use App\Modules\VentaMineral\Data\VentaMineralAnalisisLiquidacionData;
use App\Modules\VentaMineral\Data\VentaMineralComprobanteData;
use App\Modules\VentaMineral\Data\VentaMineralData;
use App\Shared\Enums\VentaMineral\AsumidoPorAnalisis;
use App\Shared\Enums\VentaMineral\ElementoQuimicoMineral;
use App\Shared\Helpers\CalculosDespacho;
use App\Shared\Responses\ApiResponse;

/**
 * Orquestacion de los analisis de laboratorio y de las liquidaciones de
 * mineral.
 */
class VentaMineralAnalisisLiquidacionService
{
    /**
     * Registra un analisis de laboratorio del despacho.
     *
     * @param array<string, mixed> $payload
     */
    public static function registrar_analisis(int $id_despacho_mineral, array $payload, int $id_empleado): array
    {
        $despacho = VentaMineralData::get_despacho($id_despacho_mineral);

        if ($despacho === null) {
            return ApiResponse::error('Despacho no encontrado', 404);
        }

        $error = self::validar_analisis($payload, $despacho);
        if ($error !== null) {
            return ApiResponse::error($error);
        }

        $idAnalisis = VentaMineralAnalisisLiquidacionData::registrar_analisis([
            'id_lote_mineral' => $despacho->id_lote_mineral,
            'id_despacho_mineral' => $id_despacho_mineral,
            'id_laboratorio' => $payload['id_laboratorio'] ?? null,
            'id_cliente' => $payload['id_cliente'] ?? null,
            'id_empleado_registro' => $id_empleado,
            'asumido_por' => (string) $payload['asumido_por'],
            'codigo_laboratorio' => $payload['codigo_laboratorio'] ?? null,
            'codigo_cliente' => self::resolver_codigo_cliente($payload, $despacho),
            'fecha_analisis' => $payload['fecha_analisis'] ?? null,
            'evidencias' => $payload['evidencias'] ?? null,
            'porcentaje_humedad' => $payload['porcentaje_humedad'] ?? 0,
            'ley_oro' => $payload['ley_oro'] ?? 0,
            'ley_plata' => $payload['ley_plata'] ?? 0,
            'ley_cobre' => $payload['ley_cobre'] ?? 0,
            'ley_zinc' => $payload['ley_zinc'] ?? 0,
            'ley_arsenico' => $payload['ley_arsenico'] ?? 0,
            'ley_hierro' => $payload['ley_hierro'] ?? 0,
            'ley_plomo' => $payload['ley_plomo'] ?? 0,
            'costo_servicio' => $payload['costo_servicio'] ?? 0,
        ]);

        $analisis = VentaMineralAnalisisLiquidacionData::get_analisis_by_id($idAnalisis);

        return ApiResponse::success($analisis, 'Analisis registrado correctamente');
    }

    /**
     * Edita un analisis de laboratorio.
     *
     * @param array<string, mixed> $payload
     */
    public static function actualizar_analisis(int $id_analisis_laboratorio, array $payload): array
    {
        $analisis = VentaMineralAnalisisLiquidacionData::get_analisis_by_id($id_analisis_laboratorio);

        if ($analisis === null) {
            return ApiResponse::error('Analisis no encontrado', 404);
        }

        $despacho = VentaMineralData::get_despacho((int) $analisis->id_despacho_mineral);

        if ($despacho === null) {
            return ApiResponse::error('Despacho no encontrado', 404);
        }

        $error = self::validar_analisis($payload, $despacho);
        if ($error !== null) {
            return ApiResponse::error($error);
        }

        $comprobanteId = $analisis->id_comprobante_analisis_despacho;
        if ($comprobanteId !== null
            && VentaMineralComprobanteData::contar_pagos('analisis', (int) $comprobanteId) > 0
        ) {
            return ApiResponse::error(
                'No se puede editar el analisis: su comprobante ya tiene pagos registrados.',
                409
            );
        }

        VentaMineralAnalisisLiquidacionData::actualizar_analisis([
            'id_analisis_laboratorio' => $id_analisis_laboratorio,
            'id_laboratorio' => $payload['id_laboratorio'] ?? null,
            'id_cliente' => $payload['id_cliente'] ?? null,
            'asumido_por' => (string) $payload['asumido_por'],
            'codigo_laboratorio' => $payload['codigo_laboratorio'] ?? null,
            'codigo_cliente' => self::resolver_codigo_cliente($payload, $despacho),
            'fecha_analisis' => $payload['fecha_analisis'] ?? null,
            'porcentaje_humedad' => $payload['porcentaje_humedad'] ?? 0,
            'ley_oro' => $payload['ley_oro'] ?? 0,
            'ley_plata' => $payload['ley_plata'] ?? 0,
            'ley_cobre' => $payload['ley_cobre'] ?? 0,
            'ley_zinc' => $payload['ley_zinc'] ?? 0,
            'ley_arsenico' => $payload['ley_arsenico'] ?? 0,
            'ley_hierro' => $payload['ley_hierro'] ?? 0,
            'ley_plomo' => $payload['ley_plomo'] ?? 0,
            'costo_servicio' => $payload['costo_servicio'] ?? 0,
        ] + (array_key_exists('evidencias', $payload) ? ['evidencias' => $payload['evidencias']] : []));

        // El comprobante de analisis suma el costo de los analisis que
        // factura: si cambia, el comprobante se recalcula.
        if ($comprobanteId !== null) {
            self::recalcular_comprobante_analisis((int) $comprobanteId);
        }

        $actualizado = VentaMineralAnalisisLiquidacionData::get_analisis_by_id($id_analisis_laboratorio);

        return ApiResponse::success($actualizado, 'Analisis actualizado correctamente');
    }

    /**
     * Elimina un analisis de laboratorio.
     */
    public static function eliminar_analisis(int $id_analisis_laboratorio): array
    {
        $analisis = VentaMineralAnalisisLiquidacionData::get_analisis_by_id($id_analisis_laboratorio);

        if ($analisis === null) {
            return ApiResponse::error('Analisis no encontrado', 404);
        }

        $comprobanteId = $analisis->id_comprobante_analisis_despacho;

        if ($comprobanteId !== null) {
            if (VentaMineralComprobanteData::contar_pagos('analisis', (int) $comprobanteId) > 0) {
                return ApiResponse::error(
                    'No se puede eliminar el analisis: su comprobante ya tiene pagos registrados.',
                    409
                );
            }

            VentaMineralAnalisisLiquidacionData::desasignar_analisis($id_analisis_laboratorio);
        }

        VentaMineralAnalisisLiquidacionData::eliminar_analisis($id_analisis_laboratorio);

        if ($comprobanteId !== null) {
            self::recalcular_comprobante_analisis((int) $comprobanteId);
        }

        return ApiResponse::success(null, 'Analisis eliminado correctamente');
    }

    /**
     * Registra una liquidacion de mineral.
     *
     * @param array<string, mixed> $payload
     */
    public static function registrar_liquidacion(int $id_despacho_mineral, array $payload, int $id_empleado): array
    {
        if (VentaMineralData::get_despacho($id_despacho_mineral) === null) {
            return ApiResponse::error('Despacho no encontrado', 404);
        }

        $error = self::validar_liquidacion($payload);
        if ($error !== null) {
            return ApiResponse::error($error);
        }

        $importes = self::calcular_importes($payload);

        $idLiquidacion = VentaMineralAnalisisLiquidacionData::registrar_liquidacion([
            'id_despacho_mineral' => $id_despacho_mineral,
            'id_cliente' => (int) $payload['id_cliente'],
            'id_empleado_registro' => $id_empleado,
            'codigo_liquidacion' => (string) $payload['codigo_liquidacion'],
            'elemento_quimico' => (string) $payload['elemento_quimico'],
            'fecha_liquidacion' => (string) $payload['fecha_liquidacion'],
            'peso_humedo' => (float) $payload['peso_humedo'],
            'tara' => (float) $payload['tara'],
            'porcentaje_humedad' => (float) $payload['porcentaje_humedad'],
            'peso_seco' => $importes['peso_seco'],
            'merma' => (float) $payload['merma'],
            'peso_neto' => $importes['peso_neto'],
            'ley_elemento' => (float) $payload['ley_elemento'],
            'porcentaje_recuperacion' => (float) $payload['porcentaje_recuperacion'],
            'maquila' => (float) ($payload['maquila'] ?? 0),
            'penalidad' => (float) ($payload['penalidad'] ?? 0),
            'valor_por_tonelada' => (float) $payload['valor_por_tonelada'],
            'porcentaje_igv' => $importes['porcentaje_igv'],
            'porcentaje_detraccion' => $importes['porcentaje_detraccion'],
            'subtotal' => $importes['subtotal'],
            'igv' => $importes['igv'],
            'total' => $importes['total'],
            'detraccion' => $importes['detraccion'],
            'neto' => $importes['neto'],
            'observaciones' => $payload['observaciones'] ?? null,
        ]);

        VentaMineralData::recalcular_estado_despacho($id_despacho_mineral);

        $liquidacion = VentaMineralAnalisisLiquidacionData::get_liquidacion($idLiquidacion);

        return ApiResponse::success($liquidacion, 'Liquidacion registrada correctamente');
    }

    /**
     * Edita una liquidacion de mineral.
     *
     * Si ya tiene un comprobante sin pagos, el comprobante se recalcula con
     * los nuevos importes.
     *
     * @param array<string, mixed> $payload
     */
    public static function actualizar_liquidacion(int $id_liquidacion_mineral, array $payload): array
    {
        $liquidacion = VentaMineralAnalisisLiquidacionData::get_liquidacion($id_liquidacion_mineral);

        if ($liquidacion === null) {
            return ApiResponse::error('Liquidacion no encontrada', 404);
        }

        $error = self::validar_liquidacion($payload);
        if ($error !== null) {
            return ApiResponse::error($error);
        }

        $comprobante = VentaMineralAnalisisLiquidacionData::get_comprobante_de_liquidacion($id_liquidacion_mineral);

        if ($comprobante !== null
            && VentaMineralComprobanteData::contar_pagos('liquidacion', (int) $comprobante->id) > 0
        ) {
            return ApiResponse::error(
                'No se puede editar la liquidacion: su comprobante ya tiene pagos registrados.',
                409
            );
        }

        $importes = self::calcular_importes($payload);

        VentaMineralAnalisisLiquidacionData::actualizar_liquidacion([
            'id_liquidacion_mineral' => $id_liquidacion_mineral,
            'id_cliente' => (int) $payload['id_cliente'],
            'codigo_liquidacion' => (string) $payload['codigo_liquidacion'],
            'elemento_quimico' => (string) $payload['elemento_quimico'],
            'fecha_liquidacion' => (string) $payload['fecha_liquidacion'],
            'peso_humedo' => (float) $payload['peso_humedo'],
            'tara' => (float) $payload['tara'],
            'porcentaje_humedad' => (float) $payload['porcentaje_humedad'],
            'peso_seco' => $importes['peso_seco'],
            'merma' => (float) $payload['merma'],
            'peso_neto' => $importes['peso_neto'],
            'ley_elemento' => (float) $payload['ley_elemento'],
            'porcentaje_recuperacion' => (float) $payload['porcentaje_recuperacion'],
            'maquila' => (float) ($payload['maquila'] ?? 0),
            'penalidad' => (float) ($payload['penalidad'] ?? 0),
            'valor_por_tonelada' => (float) $payload['valor_por_tonelada'],
            'porcentaje_igv' => $importes['porcentaje_igv'],
            'porcentaje_detraccion' => $importes['porcentaje_detraccion'],
            'subtotal' => $importes['subtotal'],
            'igv' => $importes['igv'],
            'total' => $importes['total'],
            'detraccion' => $importes['detraccion'],
            'neto' => $importes['neto'],
            'observaciones' => $payload['observaciones'] ?? null,
        ]);

        // El comprobante copia los importes de la liquidacion: si la
        // liquidacion cambia, el comprobante tambien.
        if ($comprobante !== null) {
            self::recalcular_comprobante_liquidacion((int) $comprobante->id);
        }

        VentaMineralData::recalcular_estado_despacho((int) $liquidacion->id_despacho_mineral);

        $actualizada = VentaMineralAnalisisLiquidacionData::get_liquidacion($id_liquidacion_mineral);

        return ApiResponse::success($actualizada, 'Liquidacion actualizada correctamente');
    }

    /**
     * Elimina una liquidacion de mineral.
     */
    public static function eliminar_liquidacion(int $id_liquidacion_mineral): array
    {
        $liquidacion = VentaMineralAnalisisLiquidacionData::get_liquidacion($id_liquidacion_mineral);

        if ($liquidacion === null) {
            return ApiResponse::error('Liquidacion no encontrada', 404);
        }

        $comprobante = VentaMineralAnalisisLiquidacionData::get_comprobante_de_liquidacion($id_liquidacion_mineral);

        if ($comprobante !== null) {
            if (VentaMineralComprobanteData::contar_pagos('liquidacion', (int) $comprobante->id) > 0) {
                return ApiResponse::error(
                    'No se puede eliminar la liquidacion: su comprobante ya tiene pagos registrados.',
                    409
                );
            }

            VentaMineralComprobanteData::eliminar_comprobante('liquidacion', (int) $comprobante->id);
        }

        VentaMineralAnalisisLiquidacionData::eliminar_liquidacion($id_liquidacion_mineral);
        VentaMineralData::recalcular_estado_despacho((int) $liquidacion->id_despacho_mineral);

        return ApiResponse::success(null, 'Liquidacion eliminada correctamente');
    }

    /**
     * Recalcula los importes de un comprobante de analisis a partir de los
     * analisis que factura.
     */
    public static function recalcular_comprobante_analisis(int $id_comprobante): void
    {
        $comprobante = VentaMineralComprobanteData::get_comprobante('analisis', $id_comprobante);

        if ($comprobante === null) {
            return;
        }

        $analisis = VentaMineralAnalisisLiquidacionData::get_analisis_por_comprobante([$id_comprobante]);
        $ids = array_map(fn ($a) => (int) $a->id_analisis_laboratorio, $analisis[$id_comprobante] ?? []);
        $subtotal = VentaMineralAnalisisLiquidacionData::sumar_costo_analisis($ids);

        $importes = CalculosDespacho::calcular_importes(
            subtotal: $subtotal,
            porcentajeIgv: (float) $comprobante->porcentaje_igv,
            porcentajeDetraccion: $comprobante->con_detraccion
                ? (float) $comprobante->porcentaje_detraccion
                : 0.0,
        );

        VentaMineralComprobanteData::actualizar_comprobante('analisis', [
            'id_comprobante' => $id_comprobante,
            'codigo_comprobante' => (string) $comprobante->codigo_comprobante,
            'fecha_emision' => (string) $comprobante->fecha_emision,
            'con_detraccion' => $comprobante->con_detraccion,
            'porcentaje_detraccion' => (float) $comprobante->porcentaje_detraccion,
            'porcentaje_igv' => (float) $comprobante->porcentaje_igv,
            'subtotal' => $importes['subtotal'],
            'igv' => $importes['igv'],
            'total' => $importes['total'],
            'monto_detraccion' => $importes['monto_detraccion'],
            'neto_a_pagar' => $importes['neto_a_pagar'],
        ]);
    }

    /**
     * Recalcula los importes de un comprobante de liquidacion a partir de la
     * liquidacion que lo respalda.
     */
    public static function recalcular_comprobante_liquidacion(int $id_comprobante): void
    {
        $comprobante = VentaMineralComprobanteData::get_comprobante('liquidacion', $id_comprobante);

        if ($comprobante === null || $comprobante->id_liquidacion_mineral === null) {
            return;
        }

        $liquidacion = VentaMineralAnalisisLiquidacionData::get_liquidacion((int) $comprobante->id_liquidacion_mineral);

        if ($liquidacion === null) {
            return;
        }

        $importes = CalculosDespacho::calcular_importes(
            subtotal: (float) $liquidacion->subtotal,
            porcentajeIgv: (float) $liquidacion->porcentaje_igv,
            porcentajeDetraccion: $comprobante->con_detraccion
                ? (float) $liquidacion->porcentaje_detraccion
                : 0.0,
        );

        VentaMineralComprobanteData::actualizar_comprobante('liquidacion', [
            'id_comprobante' => $id_comprobante,
            'codigo_comprobante' => (string) $comprobante->codigo_comprobante,
            'fecha_emision' => (string) $comprobante->fecha_emision,
            'con_detraccion' => $comprobante->con_detraccion,
            'porcentaje_detraccion' => (float) $comprobante->porcentaje_detraccion,
            'porcentaje_igv' => (float) $comprobante->porcentaje_igv,
            'subtotal' => $importes['subtotal'],
            'igv' => $importes['igv'],
            'total' => $importes['total'],
            'monto_detraccion' => $importes['monto_detraccion'],
            'neto_a_pagar' => $importes['neto_a_pagar'],
            'id_liquidacion_mineral' => (int) $liquidacion->id_liquidacion_mineral,
        ]);
    }

    /**
     * Calcula los importes de una liquidacion de mineral.
     *
     * peso_seco (TMS) = peso humedo - humedad - tara
     * peso_neto        = peso seco - merma
     * subtotal         = (peso neto x valor por tonelada) - deducciones
     * detraccion       = round(total x %detraccion)   ← ENTEROS
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function calcular_importes(array $payload): array
    {
        $pesoHumedo = (float) ($payload['peso_humedo'] ?? 0);
        $tara = (float) ($payload['tara'] ?? 0);
        $porcentajeHumedad = (float) ($payload['porcentaje_humedad'] ?? 0);
        $merma = (float) ($payload['merma'] ?? 0);

        $pesoHumedoMil = (int) round($pesoHumedo * 1000);
        $pctHumedadMil = (int) round($porcentajeHumedad * 100);
        $humedadMil = (int) round(($pesoHumedoMil * $pctHumedadMil) / 10000);
        $taraMil = (int) round($tara * 1000);

        $pesoSecoMil = max(0, $pesoHumedoMil - $humedadMil - $taraMil);
        $pesoSeco = $pesoSecoMil / 1000;

        $mermaMil = (int) round($merma * 1000);
        $pesoNetoMil = max(0, $pesoSecoMil - $mermaMil);
        $pesoNeto = $pesoNetoMil / 1000;

        $maquila = round((float) ($payload['maquila'] ?? 0), 2);
        $penalidad = round((float) ($payload['penalidad'] ?? 0), 2);
        $maquilaCentavos = CalculosDespacho::a_centavos($maquila);
        $penalidadCentavos = CalculosDespacho::a_centavos($penalidad);
        $totalDeducciones = ($maquilaCentavos + $penalidadCentavos) / 100;

        $valorPorTonelada = round((float) ($payload['valor_por_tonelada'] ?? 0), 2);

        // El producto del peso por el valor se redondea en CENTIMOS: el peso
        // se maneja en milesimas de tonelada y el valor en centimos. Es el
        // mismo criterio que el front.
        $valorCentavos = CalculosDespacho::a_centavos($valorPorTonelada);
        $productoCentavos = (int) round(($pesoNetoMil * $valorCentavos) / 1000);

        $subtotalCentavos = max(0, $productoCentavos - $maquilaCentavos - $penalidadCentavos);
        $subtotal = $subtotalCentavos / 100;

        $porcentajeIgv = (float) ($payload['porcentaje_igv'] ?? CalculosDespacho::IGV_POR_DEFECTO);
        $porcentajeDetraccion = (float) ($payload['porcentaje_detraccion'] ?? CalculosDespacho::DETRACCION_ESTANDAR_POR_DEFECTO);

        $igv = CalculosDespacho::calcular_igv($subtotal, $porcentajeIgv);
        $total = CalculosDespacho::round2($subtotal + $igv);
        $detraccion = CalculosDespacho::monto_detraccion($total, $porcentajeDetraccion);
        $neto = CalculosDespacho::neto_a_pagar($total, $detraccion);

        return [
            'peso_seco' => $pesoSeco,
            'peso_neto' => $pesoNeto,
            'total_deducciones' => $totalDeducciones,
            'porcentaje_igv' => $porcentajeIgv,
            'porcentaje_detraccion' => $porcentajeDetraccion,
            'subtotal' => $subtotal,
            'igv' => $igv,
            'total' => $total,
            'detraccion' => $detraccion,
            'neto' => $neto,
        ];
    }

    /**
     * Codigo del cliente del analisis.
     *
     * - Propio: lo compone la empresa con la labor del lote y los sacos
     *   despachados (ej. GENESIS-465).
     * - Cliente: lo digita el usuario.
     *
     * @param array<string, mixed> $payload
     */
    private static function resolver_codigo_cliente(array $payload, object $despacho): ?string
    {
        $asumidoPor = (string) ($payload['asumido_por'] ?? AsumidoPorAnalisis::Propio->value);

        if ($asumidoPor === AsumidoPorAnalisis::Cliente->value) {
            $codigo = trim((string) ($payload['codigo_cliente'] ?? ''));

            return $codigo === '' ? null : $codigo;
        }

        $labor = trim((string) ($despacho->labor ?? ''));

        if ($labor === '') {
            return null;
        }

        return CalculosDespacho::codigo_cliente_analisis_propio(
            $labor,
            (int) round((float) $despacho->cantidad_sacos)
        );
    }

    /**
     * Valida los datos de un analisis.
     *
     * @param array<string, mixed> $payload
     */
    private static function validar_analisis(array $payload, object $despacho): ?string
    {
        $asumidoPor = (string) ($payload['asumido_por'] ?? '');
        if (!in_array($asumidoPor, AsumidoPorAnalisis::valores(), true)) {
            return 'Debe indicar si el analisis lo asume la empresa o el cliente';
        }

        $idLaboratorio = (int) ($payload['id_laboratorio'] ?? 0);
        if ($idLaboratorio <= 0) {
            return 'Debe seleccionar el laboratorio';
        }

        if (VentaMineralCatalogoService::get_laboratorio($idLaboratorio) === null) {
            return 'El laboratorio seleccionado no existe';
        }

        if ($asumidoPor === AsumidoPorAnalisis::Cliente->value) {
            if (trim((string) ($payload['codigo_cliente'] ?? '')) === '') {
                return 'Debe indicar el codigo del cliente del analisis';
            }

            if ((int) ($payload['id_cliente'] ?? 0) <= 0) {
                return 'Debe seleccionar el cliente que asume el analisis';
            }
        }

        if ((float) ($payload['costo_servicio'] ?? 0) < 0) {
            return 'El costo del servicio no puede ser negativo';
        }

        foreach (['porcentaje_humedad'] as $campo) {
            $valor = (float) ($payload[$campo] ?? 0);
            if ($valor < 0 || $valor > 100) {
                return 'El porcentaje de humedad debe estar entre 0 y 100';
            }
        }

        return null;
    }

    /**
     * Valida los datos de una liquidacion.
     *
     * @param array<string, mixed> $payload
     */
    private static function validar_liquidacion(array $payload): ?string
    {
        if (trim((string) ($payload['codigo_liquidacion'] ?? '')) === '') {
            return 'Debe indicar el codigo de la liquidacion';
        }

        if ((int) ($payload['id_cliente'] ?? 0) <= 0) {
            return 'Debe seleccionar el cliente de la liquidacion';
        }

        if (!in_array((string) ($payload['elemento_quimico'] ?? ''), ElementoQuimicoMineral::valores(), true)) {
            return 'Debe indicar el elemento quimico de la liquidacion';
        }

        if (empty($payload['fecha_liquidacion'])) {
            return 'Debe indicar la fecha de la liquidacion';
        }

        if ((float) ($payload['peso_humedo'] ?? 0) <= 0) {
            return 'El peso humedo debe ser mayor a 0';
        }

        if ((float) ($payload['valor_por_tonelada'] ?? 0) <= 0) {
            return 'Debe indicar el valor por tonelada';
        }

        $porcentajeHumedad = (float) ($payload['porcentaje_humedad'] ?? 0);
        if ($porcentajeHumedad < 0 || $porcentajeHumedad > 100) {
            return 'El porcentaje de humedad debe estar entre 0 y 100';
        }

        $porcentajeRecuperacion = (float) ($payload['porcentaje_recuperacion'] ?? 0);
        if ($porcentajeRecuperacion < 0 || $porcentajeRecuperacion > 100) {
            return 'El porcentaje de recuperacion debe estar entre 0 y 100';
        }

        $porcentajeIgv = (float) ($payload['porcentaje_igv'] ?? CalculosDespacho::IGV_POR_DEFECTO);
        if ($porcentajeIgv < 0 || $porcentajeIgv > 100) {
            return 'El porcentaje de IGV debe estar entre 0 y 100';
        }

        $porcentajeDetraccion = (float) ($payload['porcentaje_detraccion'] ?? 0);
        if ($porcentajeDetraccion < 0 || $porcentajeDetraccion > 100) {
            return 'El porcentaje de detraccion debe estar entre 0 y 100';
        }

        return null;
    }
}
