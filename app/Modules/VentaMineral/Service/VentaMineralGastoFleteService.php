<?php

namespace App\Modules\VentaMineral\Service;

use App\Modules\VentaMineral\Data\VentaMineralComprobanteData;
use App\Modules\VentaMineral\Data\VentaMineralData;
use App\Modules\VentaMineral\Data\VentaMineralGastoFleteData;
use App\Shared\Enums\VentaMineral\TipoGastoDespacho;
use App\Shared\Helpers\CalculosDespacho;
use App\Shared\Responses\ApiResponse;

/**
 * Orquestacion de los gastos y los fletes de un despacho de mineral.
 */
class VentaMineralGastoFleteService
{
    /**
     * Registra un gasto del despacho (winche, winchadores).
     *
     * @param array<string, mixed> $payload
     */
    public static function registrar_gasto(int $id_despacho_mineral, array $payload, int $id_empleado): array
    {
        if (VentaMineralData::get_despacho($id_despacho_mineral) === null) {
            return ApiResponse::error('Despacho no encontrado', 404);
        }

        $error = self::validar_gasto($payload);
        if ($error !== null) {
            return ApiResponse::error($error);
        }

        $idGasto = VentaMineralGastoFleteData::registrar_gasto([
            'id_despacho_mineral' => $id_despacho_mineral,
            'id_empleado_registro' => $id_empleado,
            'tipo_gasto' => (string) $payload['tipo_gasto'],
            'descripcion' => $payload['descripcion'] ?? null,
            'monto' => (float) $payload['monto'],
        ]);

        $gasto = VentaMineralGastoFleteData::get_gasto($idGasto);

        return ApiResponse::success($gasto, 'Gasto registrado correctamente');
    }

    /**
     * Edita un gasto del despacho.
     *
     * @param array<string, mixed> $payload
     */
    public static function actualizar_gasto(int $id_gasto_despacho, array $payload): array
    {
        $gasto = VentaMineralGastoFleteData::get_gasto($id_gasto_despacho);

        if ($gasto === null) {
            return ApiResponse::error('Gasto no encontrado', 404);
        }

        $error = self::validar_gasto($payload);
        if ($error !== null) {
            return ApiResponse::error($error);
        }

        VentaMineralGastoFleteData::actualizar_gasto([
            'id_gasto_despacho' => $id_gasto_despacho,
            'tipo_gasto' => (string) $payload['tipo_gasto'],
            'descripcion' => $payload['descripcion'] ?? null,
            'monto' => (float) $payload['monto'],
        ]);

        $actualizado = VentaMineralGastoFleteData::get_gasto($id_gasto_despacho);

        return ApiResponse::success($actualizado, 'Gasto actualizado correctamente');
    }

    /**
     * Elimina un gasto del despacho.
     */
    public static function eliminar_gasto(int $id_gasto_despacho): array
    {
        $gasto = VentaMineralGastoFleteData::get_gasto($id_gasto_despacho);

        if ($gasto === null) {
            return ApiResponse::error('Gasto no encontrado', 404);
        }

        VentaMineralGastoFleteData::eliminar_gasto($id_gasto_despacho);

        return ApiResponse::success(null, 'Gasto eliminado correctamente');
    }

    /**
     * Registra un flete del despacho.
     *
     * Un despacho tiene UN SOLO flete: el mineral del despacho se lleva en
     * una sola unidad. Si ya hay un flete registrado, no se puede agregar
     * otro.
     *
     * @param array<string, mixed> $payload
     */
    public static function registrar_flete(int $id_despacho_mineral, array $payload, int $id_empleado): array
    {
        if (VentaMineralData::get_despacho($id_despacho_mineral) === null) {
            return ApiResponse::error('Despacho no encontrado', 404);
        }

        $fletesExistentes = VentaMineralGastoFleteData::get_fletes($id_despacho_mineral);

        if (!empty($fletesExistentes)) {
            return ApiResponse::error(
                'El despacho ya tiene un flete registrado. '
                . 'El mineral del despacho se lleva en una sola unidad: edita el flete existente.',
                409
            );
        }

        $error = self::validar_flete($payload);
        if ($error !== null) {
            return ApiResponse::error($error);
        }

        $idFlete = VentaMineralGastoFleteData::registrar_flete([
            'id_despacho_mineral' => $id_despacho_mineral,
            'id_transportista_mineral' => (int) $payload['id_transportista_mineral'],
            'id_empleado_registro' => $id_empleado,
            'placa' => $payload['placa'] ?? null,
            'licencia_conductor' => $payload['licencia_conductor'] ?? null,
            'guia_remitente' => $payload['guia_remitente'] ?? null,
            'peso_guia' => (float) ($payload['peso_guia'] ?? 0),
            // La balanza se registra despues: el flete nace sin ella.
            'codigo_ticket_balanza' => null,
            'peso_bruto' => 0,
            'tara' => 0,
            'peso_neto' => 0,
            'fecha_hora_pesaje' => null,
            'largo' => (float) ($payload['largo'] ?? 0),
            'ancho' => (float) ($payload['ancho'] ?? 0),
            'alto' => (float) ($payload['alto'] ?? 0),
            'evidencias' => $payload['evidencias'] ?? null,
        ]);

        VentaMineralData::recalcular_estado_despacho($id_despacho_mineral);

        $flete = VentaMineralGastoFleteData::get_flete($idFlete);

        return ApiResponse::success($flete, 'Flete registrado correctamente');
    }

    /**
     * Registra el peso de balanza de un flete.
     *
     * Los datos de balanza llegan DESPUES: el flete primero se despacha y
     * recien cuando llega al molino y se pesa se conoce el ticket, el bruto,
     * la tara y el neto. Por eso es una operacion aparte del alta del flete.
     *
     * @param array<string, mixed> $payload
     */
    public static function registrar_balanza(int $id_flete_despacho, array $payload, int $id_empleado): array
    {
        $flete = VentaMineralGastoFleteData::get_flete($id_flete_despacho);

        if ($flete === null) {
            return ApiResponse::error('Flete no encontrado', 404);
        }

        $error = self::validar_balanza($payload);
        if ($error !== null) {
            return ApiResponse::error($error);
        }

        // Si el flete ya esta facturado y pagado, el peso no se puede tocar:
        // las toneladas cobradas no pueden cambiar debajo de un pago.
        $comprobanteId = $flete->id_comprobante_flete_despacho;
        if ($comprobanteId !== null
            && VentaMineralComprobanteData::contar_pagos('flete', (int) $comprobanteId) > 0
        ) {
            return ApiResponse::error(
                'No se puede registrar el peso de balanza: su comprobante ya tiene pagos registrados.',
                409
            );
        }

        VentaMineralGastoFleteData::actualizar_balanza([
            'id_flete_despacho' => $id_flete_despacho,
            'codigo_ticket_balanza' => $payload['codigo_ticket_balanza'] ?? null,
            'peso_bruto' => (float) ($payload['peso_bruto'] ?? 0),
            'tara' => (float) ($payload['tara'] ?? 0),
            'peso_neto' => self::calcular_peso_neto($payload),
            'fecha_hora_pesaje' => $payload['fecha_hora_pesaje'] ?? null,
        ] + (array_key_exists('evidencias', $payload) ? ['evidencias' => $payload['evidencias']] : []));

        // Las toneladas facturadas se recalculan: el peso de balanza manda
        // sobre el peso de la guia.
        if ($comprobanteId !== null) {
            self::recalcular_comprobante_flete((int) $comprobanteId);
        }

        $actualizado = VentaMineralGastoFleteData::get_flete($id_flete_despacho);

        return ApiResponse::success($actualizado, 'Peso de balanza registrado correctamente');
    }

    /**
     * Edita un flete del despacho.
     *
     * @param array<string, mixed> $payload
     */
    public static function actualizar_flete(int $id_flete_despacho, array $payload): array
    {
        $flete = VentaMineralGastoFleteData::get_flete($id_flete_despacho);

        if ($flete === null) {
            return ApiResponse::error('Flete no encontrado', 404);
        }

        $error = self::validar_flete($payload);
        if ($error !== null) {
            return ApiResponse::error($error);
        }

        $comprobanteId = $flete->id_comprobante_flete_despacho;
        $tienePagos = $comprobanteId !== null
            && VentaMineralComprobanteData::contar_pagos('flete', (int) $comprobanteId) > 0;

        if ($tienePagos) {
            return ApiResponse::error(
                'No se puede editar el flete: su comprobante ya tiene pagos registrados.',
                409
            );
        }

        VentaMineralGastoFleteData::actualizar_flete([
            'id_flete_despacho' => $id_flete_despacho,
            'id_transportista_mineral' => (int) $payload['id_transportista_mineral'],
            'placa' => $payload['placa'] ?? null,
            'licencia_conductor' => $payload['licencia_conductor'] ?? null,
            'guia_remitente' => $payload['guia_remitente'] ?? null,
            'peso_guia' => (float) ($payload['peso_guia'] ?? 0),
            // La balanza se registra despues: el flete nace sin ella.
            'codigo_ticket_balanza' => null,
            'peso_bruto' => 0,
            'tara' => 0,
            'peso_neto' => 0,
            'fecha_hora_pesaje' => null,
            'largo' => (float) ($payload['largo'] ?? 0),
            'ancho' => (float) ($payload['ancho'] ?? 0),
            'alto' => (float) ($payload['alto'] ?? 0),
        ] + (array_key_exists('evidencias', $payload) ? ['evidencias' => $payload['evidencias']] : []));

        // Si el flete ya estaba facturado, las toneladas del comprobante se
        // recalculan: el flete sigue siendo lo que se factura.
        if ($comprobanteId !== null) {
            self::recalcular_comprobante_flete((int) $comprobanteId);
        }

        $actualizado = VentaMineralGastoFleteData::get_flete($id_flete_despacho);

        return ApiResponse::success($actualizado, 'Flete actualizado correctamente');
    }

    /**
     * Elimina un flete del despacho.
     *
     * Si el flete estaba en un comprobante sin pagos, se desasigna y el
     * comprobante se recalcula. Con pagos, no se permite.
     */
    public static function eliminar_flete(int $id_flete_despacho): array
    {
        $flete = VentaMineralGastoFleteData::get_flete($id_flete_despacho);

        if ($flete === null) {
            return ApiResponse::error('Flete no encontrado', 404);
        }

        $comprobanteId = $flete->id_comprobante_flete_despacho;

        if ($comprobanteId !== null) {
            if (VentaMineralComprobanteData::contar_pagos('flete', (int) $comprobanteId) > 0) {
                return ApiResponse::error(
                    'No se puede eliminar el flete: su comprobante ya tiene pagos registrados.',
                    409
                );
            }

            VentaMineralGastoFleteData::desasignar_flete($id_flete_despacho);
        }

        VentaMineralGastoFleteData::eliminar_flete($id_flete_despacho);
        VentaMineralData::recalcular_estado_despacho((int) $flete->id_despacho_mineral);

        if ($comprobanteId !== null) {
            self::recalcular_comprobante_flete((int) $comprobanteId);
        }

        return ApiResponse::success(null, 'Flete eliminado correctamente');
    }

    /**
     * Recalcula las toneladas y los importes de un comprobante de flete a
     * partir de los fletes que lo cubren.
     */
    public static function recalcular_comprobante_flete(int $id_comprobante): void
    {
        $comprobante = VentaMineralComprobanteData::get_comprobante('flete', $id_comprobante);

        if ($comprobante === null) {
            return;
        }

        $fletes = VentaMineralGastoFleteData::get_fletes_por_comprobante([$id_comprobante]);
        $idsFletes = array_map(fn ($f) => (int) $f->id_flete_despacho, $fletes[$id_comprobante] ?? []);
        $pesos = VentaMineralGastoFleteData::sumar_pesos_fletes($idsFletes);

        $toneladas = $pesos['toneladas_neto'];
        if ($toneladas <= 0) {
            $toneladas = $pesos['toneladas_guia'];
        }

        $subtotal = CalculosDespacho::subtotal_flete($toneladas, (float) $comprobante->precio_por_tonelada);

        $importes = CalculosDespacho::calcular_importes(
            subtotal: $subtotal,
            porcentajeIgv: (float) $comprobante->porcentaje_igv,
            porcentajeDetraccion: $comprobante->con_detraccion
                ? (float) $comprobante->porcentaje_detraccion
                : 0.0,
        );

        VentaMineralComprobanteData::actualizar_comprobante('flete', [
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
            'id_transportista_mineral' => (int) $comprobante->id_transportista_mineral,
            'toneladas_cobradas' => $toneladas,
            'precio_por_tonelada' => (float) $comprobante->precio_por_tonelada,
        ]);
    }

    /**
     * Peso neto del flete: si no se digita, se calcula del bruto menos la
     * tara.
     *
     * @param array<string, mixed> $payload
     */
    public static function calcular_peso_neto(array $payload): float
    {
        $pesoNeto = (float) ($payload['peso_neto'] ?? 0);

        if ($pesoNeto > 0) {
            return $pesoNeto;
        }

        $pesoBruto = (float) ($payload['peso_bruto'] ?? 0);
        $tara = (float) ($payload['tara'] ?? 0);

        if ($pesoBruto <= 0) {
            return 0.0;
        }

        return CalculosDespacho::round2(max(0.0, $pesoBruto - $tara));
    }

    /**
     * Valida los datos de un gasto.
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
     * Valida los datos de un flete.
     *
     * La balanza NO se valida aqui: se registra despues, en su propia
     * operacion (ver `validar_balanza`).
     *
     * @param array<string, mixed> $payload
     */
    public static function validar_flete(array $payload): ?string
    {
        $idTransportista = (int) ($payload['id_transportista_mineral'] ?? 0);
        if ($idTransportista <= 0) {
            return 'Debe seleccionar el transportista del flete';
        }

        if (VentaMineralCatalogoService::get_transportista($idTransportista) === null) {
            return 'El transportista seleccionado no existe';
        }

        if ((float) ($payload['peso_guia'] ?? 0) <= 0) {
            return 'El peso de la guia debe ser mayor a 0';
        }

        return null;
    }

    /**
     * Valida los datos del peso de balanza.
     *
     * @param array<string, mixed> $payload
     */
    public static function validar_balanza(array $payload): ?string
    {
        $pesoBruto = (float) ($payload['peso_bruto'] ?? 0);
        $tara = (float) ($payload['tara'] ?? 0);

        if ($pesoBruto <= 0) {
            return 'El peso bruto debe ser mayor a 0';
        }

        if ($tara <= 0) {
            return 'Debe indicar la tara';
        }

        if ($tara >= $pesoBruto) {
            return 'La tara no puede ser mayor o igual al peso bruto';
        }

        if (trim((string) ($payload['codigo_ticket_balanza'] ?? '')) === '') {
            return 'Debe indicar el codigo del ticket de balanza';
        }

        if (empty($payload['fecha_hora_pesaje'])) {
            return 'Debe indicar la fecha y hora del pesaje';
        }

        return null;
    }
}
