<?php

namespace App\Shared\Helpers;

/**
 * Calculos del modulo de venta de mineral.
 *
 * Concentra las reglas que el API y el front deben aplicar EXACTAMENTE
 * igual: si divergen, lo que se ve en pantalla no es lo que se guarda.
 *
 * ARITMETICA EN CENTIMOS
 *
 * Todos los importes se calculan sobre CENTIMOS ENTEROS, nunca sobre
 * doubles con decimales intermedios. Motivo: `456140.75 * 0.18` y
 * `456140.75 * 18 / 100` dan resultados distintos en IEEE-754 (82105.33 vs
 * 82105.34) y PHP y JavaScript no coinciden en el desempate. Con centimos
 * enteros, ambos lenguajes dan SIEMPRE el mismo numero.
 *
 * REGLA DE NEGOCIO (detraccion): la detraccion se cobra en soles ENTEROS.
 * Si el total es 48953 y la detraccion es del 10%, el resultado exacto es
 * 4895.30 pero se cobra 4895. El neto a pagar cambia en centimos respecto
 * del total exacto: esa diferencia ES la regla.
 */
class CalculosDespacho
{
    /** Porcentaje de IGV por defecto (Peru). */
    public const IGV_POR_DEFECTO = 18.0;

    /** Porcentaje de detraccion por defecto del comprobante de flete. */
    public const DETRACCION_FLETE_POR_DEFECTO = 4.0;

    /** Porcentaje de detraccion por defecto del resto de comprobantes. */
    public const DETRACCION_ESTANDAR_POR_DEFECTO = 12.0;

    /** Kilogramos por saco de mineral. */
    public const KILOGRAMOS_POR_SACO = 50.0;

    /**
     * Redondeo monetario a 2 decimales.
     */
    public static function round2(float $valor): float
    {
        return round($valor, 2);
    }

    /**
     * Redondeo de la detraccion a ENTEROS.
     */
    public static function round_detraccion(float $valor): float
    {
        return (float) round($valor);
    }

    /**
     * Convierte un monto en soles a CENTIMOS enteros.
     */
    public static function a_centavos(float $valor): int
    {
        return (int) round($valor * 100);
    }

    /**
     * Convierte un porcentaje a CENTESIMOS enteros (18% -> 1800).
     */
    public static function porcentaje_a_centesimos(float $porcentaje): int
    {
        return (int) round($porcentaje * 100);
    }

    /**
     * IGV en soles, calculado sobre centimos enteros.
     *
     * igvCentavos = round(subtotalCentavos * pctCentavos / 10000)
     */
    public static function calcular_igv(float $subtotal, float $porcentajeIgv): float
    {
        if ($porcentajeIgv <= 0) {
            return 0.0;
        }

        $centavos = self::a_centavos($subtotal);
        $pctCentavos = self::porcentaje_a_centesimos($porcentajeIgv);

        $igvCentavos = (int) round(($centavos * $pctCentavos) / 10000);

        return $igvCentavos / 100;
    }

    /**
     * Monto de la detraccion en soles ENTEROS.
     *
     * det = round(totalCentavos * pctCentavos / 1000000)
     */
    public static function monto_detraccion(float $total, float $porcentaje): float
    {
        if ($porcentaje <= 0) {
            return 0.0;
        }

        $centavos = self::a_centavos($total);
        $pctCentavos = self::porcentaje_a_centesimos($porcentaje);

        return (float) (int) round(($centavos * $pctCentavos) / 1000000);
    }

    /**
     * Neto a pagar: el total menos la detraccion ya redondeada.
     */
    public static function neto_a_pagar(float $total, float $montoDetraccion): float
    {
        return self::round2($total - $montoDetraccion);
    }

    /**
     * Monto de la detraccion SOLES.
     *
     * La detraccion siempre se cobra en soles: si el comprobante esta en
     * dolares, el monto se convierte con el tipo de cambio. Con la moneda en
     * soles el tipo de cambio es 1 y ambos montos coinciden.
     *
     * Se redondea a ENTEROS, igual que la detraccion: el cobro no admite
     * centavos.
     */
    public static function monto_detraccion_soles(float $montoDetraccion, float $tipoCambio): float
    {
        return (float) round($montoDetraccion * $tipoCambio);
    }

    /**
     * Cascada de importes de un comprobante de despacho.
     *
     * subtotal -> igv -> total -> detraccion (entera) -> neto a pagar.
     *
     * @return array{
     *   subtotal: float,
     *   igv: float,
     *   total: float,
     *   monto_detraccion: float,
     *   neto_a_pagar: float
     * }
     */
    public static function calcular_importes(
        float $subtotal,
        float $porcentajeIgv,
        float $porcentajeDetraccion
    ): array {
        $subtotal = self::round2($subtotal);
        $igv = self::calcular_igv($subtotal, $porcentajeIgv);
        $total = self::round2($subtotal + $igv);
        $montoDetraccion = self::monto_detraccion($total, $porcentajeDetraccion);
        $netoAPagar = self::neto_a_pagar($total, $montoDetraccion);

        return [
            'subtotal' => $subtotal,
            'igv' => $igv,
            'total' => $total,
            'monto_detraccion' => $montoDetraccion,
            'neto_a_pagar' => $netoAPagar,
        ];
    }

    /**
     * Subtotal de un comprobante de flete: toneladas x precio por tonelada.
     *
     * El producto se redondea en CENTIMOS. Las toneladas tienen 3 decimales
     * y el precio 2: el producto se calcula sobre micro-toneladas y
     * centimos enteros, sin decimales intermedios.
     *
     * productoCentavos = round(toneladasMicros * precioCentavos / 1000000)
     */
    public static function subtotal_flete(float $toneladas, float $precioPorTonelada): float
    {
        $toneladasMicros = (int) round($toneladas * 1000000);
        $precioCentavos = self::a_centavos($precioPorTonelada);

        $centavos = (int) round(($toneladasMicros * $precioCentavos) / 1000000);

        return $centavos / 100;
    }

    /**
     * Peso de la guia remitente en TONELADAS, a partir de los sacos.
     *
     * Referencia de la operacion: (sacos x 50 kg) / 1000.
     */
    public static function peso_guia_toneladas(int $cantidadSacos): float
    {
        return self::round2(($cantidadSacos * self::KILOGRAMOS_POR_SACO) / 1000);
    }

    /**
     * Codigo del cliente de un analisis PROPIO.
     *
     * Lo compone la empresa: nombre de la labor del lote despachado mas la
     * cantidad de sacos despachados. Ej. GENESIS-465.
     */
    public static function codigo_cliente_analisis_propio(string $nombreLabor, int $cantidadSacos): string
    {
        $labor = strtoupper(trim($nombreLabor));

        return $labor . '-' . $cantidadSacos;
    }
}
